<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Contracts\Routing\Exception\MethodNotAllowedExceptionInterface;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundException;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;
use Waffle\Commons\Contracts\Routing\RouterInterface;

/**
 * Middleware responsible for matching the HTTP request to a route.
 */
final readonly class CoreRoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $match = $this->router->matchRequest(request: $request);

            if ($match === null) {
                // STAB-02: surface a typed 404 rather than RuntimeException so the
                // error renderer can map it to HTTP 404 (instead of a generic 500).
                throw new RouteNotFoundException();
            }

            // We enrich the request with the controller and params found by the router.
            // Attribute names (`_classname`, `_method`, ...) remain stable PSR-7 keys —
            // the only thing that changed is the producer-side shape (MatchedRoute DTO
            // instead of a nested associative array).
            $request = $request
                ->withAttribute('_classname', $match->className)
                ->withAttribute('_method', $match->method)
                ->withAttribute('_arguments', $match->arguments)
                ->withAttribute('_path', $match->path)
                ->withAttribute('_name', $match->name)
                ->withAttribute('_params', $match->params);
        } catch (RouteNotFoundExceptionInterface|MethodNotAllowedExceptionInterface $e) {
            // Let the routing exceptions bubble up naturally to ErrorHandlerMiddleware (404 and 405)
            throw $e;
        }

        return $handler->handle($request);
    }
}
