<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Tests;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Pipeline\MiddlewareStack;
use Waffle\Commons\Pipeline\RequestHandler;
use WaffleTests\Commons\Pipeline\AbstractTestCase;

final class MiddlewareStackTest extends AbstractTestCase
{
    public function testAddAppendsMiddlewareToEnd(): void
    {
        $stack = new MiddlewareStack();
        $middleware1 = $this->createStub(MiddlewareInterface::class);
        $middleware2 = $this->createStub(MiddlewareInterface::class);

        $stack->add($middleware1);
        $stack->add($middleware2);

        $middlewares = array_values($stack->getMiddlewares());

        static::assertCount(2, $middlewares);
        [$first, $second] = $middlewares;
        static::assertSame($middleware1, $first);
        static::assertSame($middleware2, $second);
    }

    public function testPrependAddsMiddlewareToBeginning(): void
    {
        $stack = new MiddlewareStack();
        $middleware1 = $this->createStub(MiddlewareInterface::class);
        $middleware2 = $this->createStub(MiddlewareInterface::class);

        // Add M1 first
        $stack->add($middleware1);
        // Prepend M2 (should be before M1)
        $stack->prepend($middleware2);

        $middlewares = array_values($stack->getMiddlewares());

        static::assertCount(2, $middlewares);
        [$first, $second] = $middlewares;
        static::assertSame($middleware2, $first, 'Prepended middleware should be first');
        static::assertSame($middleware1, $second, 'Original middleware should be second');
    }

    public function testFluentInterface(): void
    {
        $stack = new MiddlewareStack();
        $middleware = $this->createStub(MiddlewareInterface::class);

        $resultAdd = $stack->add($middleware);
        $resultPrepend = $stack->prepend($middleware);

        static::assertSame($stack, $resultAdd);
        static::assertSame($stack, $resultPrepend);
    }

    public function testCreateHandlerReturnsConfiguredRequestHandler(): void
    {
        $stack = new MiddlewareStack();
        $fallbackHandler = $this->createStub(RequestHandlerInterface::class);
        $middleware = $this->createStub(MiddlewareInterface::class);

        $stack->add($middleware);

        $handler = $stack->createHandler($fallbackHandler);

        // We verify that the factory method returns the correct concrete implementation
        static::assertInstanceOf(RequestHandler::class, $handler);
    }

    public function testAddAfterCreateHandlerThrows(): void
    {
        $stack = new MiddlewareStack();
        $stack->createHandler($this->createStub(RequestHandlerInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MiddlewareStack is locked');

        $stack->add($this->createStub(MiddlewareInterface::class));
    }

    public function testPrependAfterCreateHandlerThrows(): void
    {
        $stack = new MiddlewareStack();
        $stack->createHandler($this->createStub(RequestHandlerInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MiddlewareStack is locked');

        $stack->prepend($this->createStub(MiddlewareInterface::class));
    }
}
