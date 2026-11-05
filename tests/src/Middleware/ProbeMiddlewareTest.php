<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Contracts\Health\HealthResult;
use Waffle\Commons\Pipeline\Health\ReadinessProbe;
use Waffle\Commons\Pipeline\Middleware\ProbeMiddleware;
use WaffleTests\Commons\Pipeline\AbstractTestCase;
use WaffleTests\Commons\Pipeline\Fixture\StubHealthCheck;

#[CoversClass(ProbeMiddleware::class)]
final class ProbeMiddlewareTest extends AbstractTestCase
{
    private Psr17Factory $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
    }

    public function testLivenessAnswersOkWithoutRunningAnyProbe(): void
    {
        $middleware = $this->middleware(new ReadinessProbe([
            new StubHealthCheck('db.pool', HealthResult::down('connection refused')),
        ]));

        $response = $middleware->process($this->request('/healthz'), $this->unreachable());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"status":"ok"}', (string) $response->getBody());
        static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        static::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testReadinessAnswersOkWhenReady(): void
    {
        $response = $this->middleware($this->readyProbe())->process($this->request('/readyz'), $this->unreachable());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"status":"ok"}', (string) $response->getBody());
    }

    public function testReadinessAnswers503WithAMinimalBodyWhenNotReady(): void
    {
        $response = $this->middleware($this->notReadyProbe())->process(
            $this->request('/readyz', '203.0.113.9'),
            $this->unreachable(),
        );

        static::assertSame(503, $response->getStatusCode());
        static::assertSame('{"status":"unavailable"}', (string) $response->getBody());
    }

    public function testAnAllowListedAddressGetsThePerProbeReport(): void
    {
        $response = $this->middleware($this->notReadyProbe(), allowedIps: ['127.0.0.1'])->process(
            $this->request('/readyz', '127.0.0.1'),
            $this->unreachable(),
        );

        static::assertSame(503, $response->getStatusCode());
        $report = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        static::assertIsArray($report);
        static::assertSame('unavailable', $report['status']);
        static::assertSame(['redis'], $report['missing']);
        static::assertFalse($report['draining']);
        static::assertIsArray($report['checks']);
        static::assertIsArray($report['checks']['db.pool']);
        static::assertSame('down', $report['checks']['db.pool']['status']);
        static::assertTrue($report['checks']['db.pool']['critical']);
        static::assertSame('connection refused', $report['checks']['db.pool']['detail']);
        static::assertIsFloat($report['checks']['db.pool']['duration_ms']);
    }

    public function testTheBearerTokenGetsThePerProbeReport(): void
    {
        $token = bin2hex(random_bytes(16));

        $response = $this->middleware($this->readyProbe(), token: $token)->process(
            $this->request('/readyz', '203.0.113.9')->withHeader('Authorization', 'Bearer ' . $token),
            $this->unreachable(),
        );

        $report = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        static::assertIsArray($report);
        static::assertArrayHasKey('checks', $report);
    }

    public function testAWrongTokenGetsTheMinimalBody(): void
    {
        $token = bin2hex(random_bytes(16));

        $response = $this->middleware($this->readyProbe(), token: $token)->process(
            $this->request('/readyz', '203.0.113.9')->withHeader('Authorization', 'Bearer guess'),
            $this->unreachable(),
        );

        static::assertSame('{"status":"ok"}', (string) $response->getBody());
    }

    public function testARequestWithoutAnAddressGetsTheMinimalBody(): void
    {
        $request = $this->factory->createServerRequest('GET', '/readyz');

        $response = $this->middleware($this->readyProbe(), allowedIps: ['127.0.0.1'])->process(
            $request,
            $this->unreachable(),
        );

        static::assertSame('{"status":"ok"}', (string) $response->getBody());
    }

    public function testEveryOtherPathPassesThrough(): void
    {
        $passed = $this->factory->createResponse(204);
        $handler = new class($passed) implements RequestHandlerInterface {
            public function __construct(
                private readonly ResponseInterface $response,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $response = $this->middleware($this->readyProbe())->process($this->request('/api/orders'), $handler);

        static::assertSame($passed, $response);
    }

    /** @param list<string> $allowedIps */
    private function middleware(
        ReadinessProbe $probe,
        #[\SensitiveParameter]
        ?string $token = null,
        array $allowedIps = [],
    ): ProbeMiddleware {
        return new ProbeMiddleware($probe, $this->factory, $this->factory, $token, $allowedIps);
    }

    private function request(string $path, string $remoteAddress = '10.0.0.1'): ServerRequestInterface
    {
        return $this->factory->createServerRequest('GET', $path, ['REMOTE_ADDR' => $remoteAddress]);
    }

    private function readyProbe(): ReadinessProbe
    {
        return new ReadinessProbe([new StubHealthCheck('db.pool', HealthResult::up())]);
    }

    private function notReadyProbe(): ReadinessProbe
    {
        return new ReadinessProbe([new StubHealthCheck('db.pool', HealthResult::down('connection refused'))], [
            'db.pool',
            'redis',
        ]);
    }

    private function unreachable(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('A probe path must not reach the application.');
            }
        };
    }
}
