<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Pipeline\RequestHandler;

final class RequestHandlerTest extends AbstractTestCase
{
    public function testItExecutesMiddlewareStackInOrder(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->once())->method('handle')->willReturn($response);

        $log = [];

        $middleware1 = new class($log) implements MiddlewareInterface {
            public function __construct(
                private array &$log,
            ) {}

            #[\Override]
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->log[] = 'M1-In';
                $response = $handler->handle($request);
                $this->log[] = 'M1-Out';
                return $response;
            }
        };

        $middleware2 = new class($log) implements MiddlewareInterface {
            public function __construct(
                private array &$log,
            ) {}

            #[\Override]
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->log[] = 'M2-In';
                $response = $handler->handle($request);
                $this->log[] = 'M2-Out';
                return $response;
            }
        };

        $handler = new RequestHandler([$middleware1, $middleware2], $fallback);
        $result = $handler->handle($request);

        static::assertSame($response, $result);
        static::assertSame(['M1-In', 'M2-In', 'M2-Out', 'M1-Out'], $log);
    }

    public function testMiddlewareCanShortCircuitThePipeline(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $expectedResponse = $this->createStub(ResponseInterface::class);

        // Fallback should NEVER be called because middleware stops the chain
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->never())->method('handle');

        $shortCircuitMiddleware = $this->createMock(MiddlewareInterface::class);
        $shortCircuitMiddleware->expects($this->once())->method('process')->willReturn($expectedResponse);

        $secondMiddleware = $this->createMock(MiddlewareInterface::class);
        $secondMiddleware->expects($this->never())->method('process');

        $handler = new RequestHandler([$shortCircuitMiddleware, $secondMiddleware], $fallback);
        $result = $handler->handle($request);

        static::assertSame($expectedResponse, $result);
    }

    public function testHandlerIsImmutableAndReusable(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        // Fallback should be called TWICE because we run the handler twice
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->exactly(2))->method('handle')->willReturn($response);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware
            ->expects($this->exactly(2))
            ->method('process')
            ->willReturnCallback(static fn($req, $next) => $next->handle($req));

        $handler = new RequestHandler([$middleware], $fallback);

        // First execution
        $handler->handle($request);

        // Second execution (should verify that the internal queue was not emptied)
        $handler->handle($request);
    }
}
