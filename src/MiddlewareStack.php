<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Pipeline\MiddlewareStackInterface;

final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var array<MiddlewareInterface>
     */
    public private(set) array $middlewares = [];

    private bool $locked = false;

    private function ensureUnlocked(): void
    {
        if ($this->locked) {
            throw new RuntimeException('MiddlewareStack is locked and cannot be modified during request processing.');
        }
    }

    public function add(MiddlewareInterface $middleware): static
    {
        $this->ensureUnlocked();
        // @igor-ignore: boot-time pipeline assembly; the locked latch (see createHandler) forbids request-time mutation, so this never leaks across requests.
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function prepend(MiddlewareInterface $middleware): static
    {
        $this->ensureUnlocked();
        array_unshift($this->middlewares, $middleware);

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function createHandler(RequestHandlerInterface $fallbackHandler): RequestHandlerInterface
    {
        // @igor-ignore: idempotent latch flipped on first handler build; freezes the boot-time stack, it is not per-request state.
        $this->locked = true;

        return new RequestHandler($this->middlewares, $fallbackHandler);
    }
}
