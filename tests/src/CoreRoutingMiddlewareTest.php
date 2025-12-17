<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Constant\Constant;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;
use Waffle\Commons\Contracts\Routing\RouterInterface;
use Waffle\Commons\Pipeline\CoreRoutingMiddleware;

#[AllowMockObjectsWithoutExpectations]
final class CoreRoutingMiddlewareTest extends AbstractTestCase
{
    public function testItMatchesRouteAndEnrichesRequest(): void
    {
        // 1. Setup Data
        $routeMatch = [
            Constant::CLASSNAME => 'TestController',
            Constant::METHOD => 'success',
            Constant::ARGUMENTS => ['id' => 123],
            Constant::PATH => '/path_to_route',
            Constant::NAME => 'home',
            Constant::PARAMS => ['int' => 123],
        ];

        // 2. Create Request Mock FIRST (We must use this exact instance everywhere)
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        // 3. Configure Router Expectation using the Mock
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('matchRequest')->with($request)->willReturn($routeMatch);

        // 4. Configure Request Immutability Chain (Mocking the fluent interface)
        $req1 = $this->createMock(ServerRequestInterface::class);
        $req2 = $this->createMock(ServerRequestInterface::class);
        $req3 = $this->createMock(ServerRequestInterface::class);
        $req4 = $this->createMock(ServerRequestInterface::class);
        $req5 = $this->createMock(ServerRequestInterface::class);
        $req6 = $this->createMock(ServerRequestInterface::class);

        $request
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_classname', $routeMatch[Constant::CLASSNAME])
            ->willReturn($req1);
        $req1
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_method', $routeMatch[Constant::METHOD])
            ->willReturn($req2);
        $req2
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_arguments', $routeMatch[Constant::ARGUMENTS])
            ->willReturn($req3);
        $req3
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_path', $routeMatch[Constant::PATH])
            ->willReturn($req4);
        $req4
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_name', $routeMatch[Constant::NAME])
            ->willReturn($req5);
        $req5
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_params', $routeMatch[Constant::PARAMS])
            ->willReturn($req6);

        // 5. Configure Handler to receive the final enriched request
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($req6)->willReturn($response);

        // 6. Execute
        $middleware = new CoreRoutingMiddleware($router);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testItHandlesRouteWithoutOptionalParams(): void
    {
        // FIX 1: Use createMock instead of createStub because we use ->expects() below
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        // Route without 'params' key
        $routeMatch = [
            Constant::CLASSNAME => 'TestController',
            Constant::METHOD => 'index',
            Constant::ARGUMENTS => [],
            Constant::PATH => '/',
            Constant::NAME => 'home',
            // No params
        ];

        $router = $this->createMock(RouterInterface::class);
        $router->method('matchRequest')->willReturn($routeMatch);

        // Setup chain
        $req1 = $this->createStub(ServerRequestInterface::class);
        $req2 = $this->createStub(ServerRequestInterface::class);
        $req3 = $this->createStub(ServerRequestInterface::class);
        $req4 = $this->createStub(ServerRequestInterface::class);
        $req5 = $this->createStub(ServerRequestInterface::class);
        $req6 = $this->createStub(ServerRequestInterface::class);

        // We use method() here which works on Mocks (createMock), but allows defining return values without strict ordering if not needed
        // But for the chain to work, the first call MUST be on $request.
        $request->method('withAttribute')->willReturn($req1);
        $req1->method('withAttribute')->willReturn($req2);
        $req2->method('withAttribute')->willReturn($req3);
        $req3->method('withAttribute')->willReturn($req4);
        $req4->method('withAttribute')->willReturn($req5);

        // Specifically check that empty array is used for missing params
        // This expects() call was failing because $req5 was a Stub in previous logic, now it's a Stub but we check expectation on it?
        // Wait, creating a Stub with createStub() does NOT allow expects().
        // To fix this cleanly: We should use Mocks for the whole chain if we want to verify calls.
        // Or simpler: Just return a new mock at the end and check arguments.

        // Let's redefine the chain as Mocks to be safe for expects()
        $req5 = $this->createMock(ServerRequestInterface::class);
        $req4 = $this->createMock(ServerRequestInterface::class);
        $req4->method('withAttribute')->willReturn($req5);
        // ... (simplified back propagation) ...

        // Actually, to fix "Call to undefined method ::expects()", we just need to change the creation of $req5 to createMock.
        // But $req5 comes from $req4->withAttribute...

        // LET'S SIMPLIFY:
        // We will use createMock for EVERYTHING in this test method to allow expects().
        $request = $this->createMock(ServerRequestInterface::class);
        $req1 = $this->createMock(ServerRequestInterface::class);
        $req2 = $this->createMock(ServerRequestInterface::class);
        $req3 = $this->createMock(ServerRequestInterface::class);
        $req4 = $this->createMock(ServerRequestInterface::class);
        $req5 = $this->createMock(ServerRequestInterface::class);
        $req6 = $this->createMock(ServerRequestInterface::class);

        $request->method('withAttribute')->willReturn($req1);
        $req1->method('withAttribute')->willReturn($req2);
        $req2->method('withAttribute')->willReturn($req3);
        $req3->method('withAttribute')->willReturn($req4);
        $req4->method('withAttribute')->willReturn($req5);

        $req5->expects($this->once())->method('withAttribute')->with('_params', [])->willReturn($req6);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new CoreRoutingMiddleware($router);
        $middleware->process($request, $handler);
    }

    public function testItBubblesExceptionWhenRouteNotFound(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $router = $this->createMock(RouterInterface::class);

        // FIX 2: We must throw a real Exception/Throwable, not just an interface Mock.
        // We create an anonymous class that extends RuntimeException and implements the interface.
        $exception = new class extends RuntimeException implements RouteNotFoundExceptionInterface {};

        $router->expects($this->once())->method('matchRequest')->willThrowException($exception);

        $middleware = new CoreRoutingMiddleware($router);

        $this->expectException(RouteNotFoundExceptionInterface::class);

        $middleware->process($request, $handler);
    }

    public function testItThrowsRuntimeExceptionWhenRouterReturnsNull(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $router = $this->createMock(RouterInterface::class);

        // Simulate Router returning null (no match found)
        $router->expects($this->once())->method('matchRequest')->with($request)->willReturn(null);

        $middleware = new CoreRoutingMiddleware($router);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route not found.');

        // The handler should never be called if route is missing
        $handler->expects($this->never())->method('handle');

        $middleware->process($request, $handler);
    }
}
