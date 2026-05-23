<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundException;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;
use Waffle\Commons\Contracts\Routing\MatchedRoute;
use Waffle\Commons\Contracts\Routing\RouterInterface;
use Waffle\Commons\Pipeline\CoreRoutingMiddleware;

#[AllowMockObjectsWithoutExpectations]
final class CoreRoutingMiddlewareTest extends AbstractTestCase
{
    public function testItMatchesRouteAndEnrichesRequest(): void
    {
        // 1. Setup Data — MatchedRoute is now the producer-side contract.
        $routeMatch = new MatchedRoute(
            className: 'TestController',
            method: 'success',
            arguments: ['id' => 123],
            path: '/path_to_route',
            name: 'home',
            params: ['int' => 123],
        );

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
            ->with('_classname', $routeMatch->className)
            ->willReturn($req1);
        $req1->expects($this->once())->method('withAttribute')->with('_method', $routeMatch->method)->willReturn($req2);
        $req2
            ->expects($this->once())
            ->method('withAttribute')
            ->with('_arguments', $routeMatch->arguments)
            ->willReturn($req3);
        $req3->expects($this->once())->method('withAttribute')->with('_path', $routeMatch->path)->willReturn($req4);
        $req4->expects($this->once())->method('withAttribute')->with('_name', $routeMatch->name)->willReturn($req5);
        $req5->expects($this->once())->method('withAttribute')->with('_params', $routeMatch->params)->willReturn($req6);

        // 5. Configure Handler to receive the final enriched request
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($req6)->willReturn($response);

        // 6. Execute
        $middleware = new CoreRoutingMiddleware($router);
        $result = $middleware->process($request, $handler);

        static::assertSame($response, $result);
    }

    public function testItHandlesRouteWithoutOptionalParams(): void
    {
        // 1. Setup — empty params (default) replaces the previous "params=null" idiom;
        //    MatchedRoute::$params is non-nullable (default []), enforcing the invariant
        //    at the type-system level instead of at runtime.
        $routeMatch = new MatchedRoute(
            className: 'TestController',
            method: 'index',
            arguments: [],
            path: '/',
            name: 'home',
        );

        $router = $this->createMock(RouterInterface::class);
        $router->method('matchRequest')->willReturn($routeMatch);

        $response = $this->createStub(ResponseInterface::class);

        // 2. Mock Chain - Use createMock to allow expects() on any link if needed
        $request = $this->createMock(ServerRequestInterface::class);
        $req1 = $this->createMock(ServerRequestInterface::class);
        $req2 = $this->createMock(ServerRequestInterface::class);
        $req3 = $this->createMock(ServerRequestInterface::class);
        $req4 = $this->createMock(ServerRequestInterface::class);
        $req5 = $this->createMock(ServerRequestInterface::class);
        $req6 = $this->createMock(ServerRequestInterface::class);

        // Chain configuration
        $request->method('withAttribute')->willReturn($req1);
        $req1->method('withAttribute')->willReturn($req2);
        $req2->method('withAttribute')->willReturn($req3);
        $req3->method('withAttribute')->willReturn($req4);
        $req4->method('withAttribute')->willReturn($req5);

        // 3. Assertion: Ensure 'params' attribute is set to the empty array (DTO default)
        $req5->expects($this->once())->method('withAttribute')->with('_params', [])->willReturn($req6);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($req6)->willReturn($response);

        // 4. Execution
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

    public function testItThrowsRouteNotFoundExceptionWhenRouterReturnsNull(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $router = $this->createMock(RouterInterface::class);

        // Simulate Router returning null (no match found)
        $router->expects($this->once())->method('matchRequest')->with($request)->willReturn(null);

        $middleware = new CoreRoutingMiddleware($router);

        // STAB-02: middleware must surface a typed 404, not a generic 500.
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Route not found.');

        // The handler should never be called if route is missing
        $handler->expects($this->never())->method('handle');

        $middleware->process($request, $handler);
    }

    public function testRouteNotFoundExceptionImplementsContractInterface(): void
    {
        // Belt-and-braces: the typed exception MUST still satisfy the marker
        // interface that downstream renderers/handlers catch on.
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $router->method('matchRequest')->willReturn(null);

        $middleware = new CoreRoutingMiddleware($router);

        try {
            $middleware->process($request, $handler);
            static::fail('Expected RouteNotFoundException.');
        } catch (RouteNotFoundExceptionInterface $caught) {
            static::assertInstanceOf(RuntimeException::class, $caught);
        }
    }
}
