<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Middleware;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware;
use WaffleTests\Commons\Pipeline\AbstractTestCase;

#[AllowMockObjectsWithoutExpectations]
final class TrustedHostMiddlewareTest extends AbstractTestCase
{
    private function requestWithHost(string $host): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getHost')->willReturn($host);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    public function testEmptyAllowlistPassesThrough(): void
    {
        $middleware = new TrustedHostMiddleware([]);
        $request = $this->requestWithHost('anything.example');
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        static::assertSame($response, $result);
    }

    public function testExactMatchInvokesHandler(): void
    {
        $middleware = new TrustedHostMiddleware(['trusted.example']);
        $request = $this->requestWithHost('trusted.example');
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        static::assertSame($response, $middleware->process($request, $handler));
    }

    public function testCaseInsensitiveMatchInvokesHandler(): void
    {
        $middleware = new TrustedHostMiddleware(['Trusted.Example']);
        // PSR-7 normalizes host to lowercase via UriInterface::getHost(), but we stub it directly here.
        $request = $this->requestWithHost('trusted.example');
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        static::assertSame($response, $middleware->process($request, $handler));
    }

    public function testPortIsStrippedBeforeComparison(): void
    {
        // We rely on UriInterface::getHost() returning the host WITHOUT the port — PSR-7 guarantee.
        // This test documents that the middleware does NOT need to parse ports itself.
        $middleware = new TrustedHostMiddleware(['trusted.example']);
        $request = $this->requestWithHost('trusted.example'); // port already stripped by Uri
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($response);

        static::assertSame($response, $middleware->process($request, $handler));
    }

    public function testEmptyHostThrows(): void
    {
        $middleware = new TrustedHostMiddleware(['trusted.example']);
        $request = $this->requestWithHost('');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Host header.');

        $middleware->process($request, $handler);
    }

    public function testUntrustedHostThrows(): void
    {
        $middleware = new TrustedHostMiddleware(['trusted.example']);
        $request = $this->requestWithHost('evil.example');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Untrusted Host "evil.example".');

        $middleware->process($request, $handler);
    }

    public function testUntrustedHostMessageDoesNotLeakAllowlist(): void
    {
        // Information disclosure guardrail: the exception message must not include
        // the configured allowlist values.
        $middleware = new TrustedHostMiddleware(['secret-internal.example', 'admin.internal.local']);
        $request = $this->requestWithHost('evil.example');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        try {
            $middleware->process($request, $handler);
            static::fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            static::assertStringNotContainsString('secret-internal.example', $e->getMessage());
            static::assertStringNotContainsString('admin.internal.local', $e->getMessage());
        }
    }

    public function testHandlerReceivesRequestUnchangedOnSuccess(): void
    {
        $middleware = new TrustedHostMiddleware(['trusted.example']);
        $request = $this->requestWithHost('trusted.example');
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        // The middleware must pass the SAME request instance, not a clone or wrapped variant.
        $handler->expects($this->once())->method('handle')->with(static::identicalTo($request))->willReturn($response);

        $middleware->process($request, $handler);
    }
}
