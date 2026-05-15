<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Pipeline\Middleware\SecureHeadersMiddleware;
use WaffleTests\Commons\Pipeline\AbstractTestCase;

#[AllowMockObjectsWithoutExpectations]
final class SecureHeadersMiddlewareTest extends AbstractTestCase
{
    public function testAddsDefaultSecurityHeaders(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(3))->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $middleware = new SecureHeadersMiddleware();

        $result = $middleware->process($request, $handler);

        static::assertSame($response, $result);
    }

    public function testPassesThroughCustomContentSecurityPolicy(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);

        // Capture each call so we can assert the CSP value was forwarded.
        $headerCalls = [];
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('withHeader')
            ->willReturnCallback(function (string $name, string $value) use (
                &$headerCalls,
                $response,
            ): ResponseInterface {
                $headerCalls[$name] = $value;
                return $response;
            });

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($response);

        $customCsp = "default-src 'self'; script-src 'self' 'unsafe-inline'";
        $middleware = new SecureHeadersMiddleware($customCsp);

        $middleware->process($request, $handler);

        static::assertSame('nosniff', $headerCalls['X-Content-Type-Options'] ?? null);
        static::assertSame('DENY', $headerCalls['X-Frame-Options'] ?? null);
        static::assertSame($customCsp, $headerCalls['Content-Security-Policy'] ?? null);
    }
}
