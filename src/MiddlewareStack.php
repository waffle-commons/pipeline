<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline;

use IgorPhp\IgorBundle\Attribute\WorkerSafe;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Pipeline\MiddlewareStackInterface;

final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var array<MiddlewareInterface>
     */
    #[WorkerSafe(reason: 'boot-time pipeline assembly; frozen by the locked latch before request-time')]
    public private(set) array $middlewares = [];

    #[WorkerSafe(
        reason: 'idempotent latch flipped on first handler build; freezes the boot-time stack, not per-request state',
    )]
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
        $this->locked = true;

        return new RequestHandler($this->middlewares, $fallbackHandler);
    }
}
