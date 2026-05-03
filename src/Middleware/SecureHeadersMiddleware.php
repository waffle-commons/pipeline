<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class SecureHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $contentSecurityPolicy = "default-src 'self'",
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Content-Security-Policy', $this->contentSecurityPolicy);
    }
}
