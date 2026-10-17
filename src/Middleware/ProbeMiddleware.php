<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Pipeline\Health\ReadinessProbe;
use Waffle\Commons\Pipeline\Health\ReadinessReport;

/**
 * Serves the orchestrator probes ([OPS-01], RFC-023 §3.2): `/healthz` (liveness) and `/readyz`
 * (readiness). Every other path passes straight through. Stateless.
 *
 * - `/healthz` answers 200 while the worker can serve a request; it never checks a dependency, or one
 *   database blip would restart every pod.
 * - `/readyz` runs the probes and answers 200 when ready, 503 otherwise.
 * - The public body is minimal (`{"status":"ok"}` or `{"status":"unavailable"}`): dependency topology is
 *   reconnaissance data. The per-probe report goes only to the bearer token or an allow-listed
 *   `REMOTE_ADDR`, the access rule of `/waffle-metrics`.
 *
 * Mount it before `TrustedHostMiddleware` (RFC-023 §5.2): kubelet probes carry the pod IP as `Host`. During
 * a shutdown, the drain marker makes `/readyz` answer 503 while every other path is still served
 * (RFC-023 §5.1).
 */
final readonly class ProbeMiddleware implements MiddlewareInterface
{
    public const string LIVENESS_PATH = '/healthz';

    public const string READINESS_PATH = '/readyz';

    /**
     * @param list<string> $allowedIps Exact `REMOTE_ADDR` values that get the per-probe report.
     */
    public function __construct(
        private ReadinessProbe $readiness,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        #[\SensitiveParameter]
        private ?string $bearerToken = null,
        private array $allowedIps = [],
    ) {}

    /** @throws \InvalidArgumentException From the PSR-17 factories on an invalid status code or stream. */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path === self::LIVENESS_PATH) {
            return $this->json(200, '{"status":"ok"}');
        }

        if ($path !== self::READINESS_PATH) {
            return $handler->handle($request);
        }

        $report = $this->readiness->run();
        $ready = $report->isReady();

        return $this->json(
            $ready ? 200 : 503,
            $this->authorised($request) ? self::detailedBody($report, $ready) : self::publicBody($ready),
        );
    }

    private static function publicBody(bool $ready): string
    {
        return $ready ? '{"status":"ok"}' : '{"status":"unavailable"}';
    }

    private static function detailedBody(ReadinessReport $report, bool $ready): string
    {
        $checks = [];
        foreach ($report->outcomes as $outcome) {
            $checks[$outcome->name] = [
                'status' => $outcome->result->status->value,
                'critical' => $outcome->critical,
                'duration_ms' => round($outcome->durationSeconds * 1000, 3),
                'detail' => $outcome->result->detail,
            ];
        }

        $json = json_encode(
            [
                'status' => $ready ? 'ok' : 'unavailable',
                'draining' => $report->draining,
                'checks' => $checks,
                'missing' => $report->missing,
            ],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return $json === false ? self::publicBody($ready) : $json;
    }

    private function json(int $status, string $body): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }

    private function authorised(ServerRequestInterface $request): bool
    {
        if ($this->bearerToken !== null) {
            $presented = $request->getHeaderLine('Authorization');
            if ($presented !== '' && hash_equals('Bearer ' . $this->bearerToken, $presented)) {
                return true;
            }
        }

        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remote) && in_array($remote, $this->allowedIps, true);
    }
}
