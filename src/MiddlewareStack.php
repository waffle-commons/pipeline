<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Contracts\Pipeline\MiddlewareStackInterface;

/**
 * Standard implementation of the middleware stack.
 */
final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var array<MiddlewareInterface>
     */
    public private(set) array $middlewares = [];

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function prepend(MiddlewareInterface $middleware): self
    {
        array_unshift($this->middlewares, $middleware);

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function createHandler(RequestHandlerInterface $fallbackHandler): RequestHandlerInterface
    {
        // This is the implementation detail hidden from the Core.
        return new RequestHandler($this->middlewares, $fallbackHandler);
    }

}
