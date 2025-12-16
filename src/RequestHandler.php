<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The pipeline execution engine (Chain of Responsibility Pattern).
 *
 * It consumes the middleware stack one by one.
 * Once the stack is empty, it executes the "fallback handler" (usually the controller).
 */
final readonly class RequestHandler implements RequestHandlerInterface
{
    /**
     * @param array<int, MiddlewareInterface> $middlewareQueue
     * @param RequestHandlerInterface $fallbackHandler The terminal handler (e.g., Controller Dispatcher)
     */
    public function __construct(
        private array $middlewareQueue,
        private RequestHandlerInterface $fallbackHandler
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 1. If the stack is empty, we have traversed all middlewares.
        // We call the "fallback" handler (the core of the onion).
        if (empty($this->middlewareQueue)) {
            return $this->fallbackHandler->handle($request);
        }

        // 2. Dequeue the next middleware.
        $middlewares = $this->middlewareQueue;
        $middleware = array_shift($middlewares);

        // 3. Create a new handler for the rest of the chain (Immutability).
        $nextHandler = new self($middlewares, $this->fallbackHandler);

        // 4. Execute the middleware passing the next handler.
        // The middleware will decide whether to call $nextHandler->handle() or return a response directly.
        return $middleware->process($request, $nextHandler);
    }
}
