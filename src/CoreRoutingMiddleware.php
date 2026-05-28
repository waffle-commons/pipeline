<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Waffle\Commons\Contracts\Routing\Exception\MethodNotAllowedExceptionInterface;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundException;
use Waffle\Commons\Contracts\Routing\RouterInterface;

/**
 * Middleware responsible for matching the HTTP request to a route.
 *
 * When a response factory is supplied, an OPTIONS request to a known path is answered
 * automatically with `204 No Content` and an `Allow` header (CORS-style preflight),
 * short-circuiting the 405 path before it is logged as an exception. Without a factory,
 * OPTIONS simply falls back to the normal 405 behaviour.
 */
final readonly class CoreRoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouterInterface $router,
        private ?ResponseFactoryInterface $responseFactory = null,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $match = $this->router->matchRequest(request: $request);
        } catch (Throwable $e) {
            // The router signals an unsupported HTTP method via MethodNotAllowedExceptionInterface.
            // For an OPTIONS preflight to a known path we answer 204 with the Allow header it
            // carries (when a response factory is wired), so a routine preflight never surfaces
            // as a logged exception. Everything else — a genuine 405 or any unrelated error —
            // is re-thrown unchanged for ErrorHandlerMiddleware to render.
            $responseFactory = $this->responseFactory;
            if (
                $e instanceof MethodNotAllowedExceptionInterface
                && $responseFactory !== null
                && $this->isOptionsRequest($request)
            ) {
                $allowedMethods = $e->getAllowedMethods();

                return $responseFactory->createResponse(204)->withHeader('Allow', implode(', ', $allowedMethods));
            }

            throw $e;
        }

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

        return $handler->handle($request);
    }

    /**
     * Case-insensitive check for the OPTIONS verb (HTTP methods are case-insensitive tokens).
     */
    private function isOptionsRequest(ServerRequestInterface $request): bool
    {
        return strtoupper($request->getMethod()) === 'OPTIONS';
    }
}
