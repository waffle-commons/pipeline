<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware enforcing a trusted-host allowlist (Alpha 6 P0, RFC-003 §3.2).
 *
 * Mounted immediately after ErrorHandlerMiddleware and before CoreRoutingMiddleware, this
 * provides fail-fast rejection of Host Header Injection / Cache Poisoning attempts. An
 * empty allowlist disables the check (DEV convenience); production deployments MUST
 * configure `waffle.trusted_hosts`.
 *
 * On rejection an InvalidArgumentException is thrown, which the wrapping
 * ErrorHandlerMiddleware + JsonErrorRenderer convert into an RFC 7807 HTTP 400 response.
 */
final readonly class TrustedHostMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string> Lowercased allowlist (computed once at construction).
     */
    private array $allowlist;

    /**
     * @param list<string> $trustedHosts Exact host strings; matched case-insensitively.
     *                                   Pass an empty list to disable the check.
     */
    public function __construct(array $trustedHosts)
    {
        $this->allowlist = array_values(array_map(strtolower(...), $trustedHosts));
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->allowlist === []) {
            return $handler->handle($request);
        }

        // PSR-7's UriInterface::getHost() returns the host without the port, lowercased.
        $host = $request->getUri()->getHost();

        if ($host === '') {
            throw new InvalidArgumentException('Missing Host header.');
        }

        if (!in_array($host, $this->allowlist, strict: true)) {
            // Intentionally omit the allowlist contents from the exception message
            // to avoid information disclosure.
            throw new InvalidArgumentException(sprintf('Untrusted Host "%s".', $host));
        }

        return $handler->handle($request);
    }
}
