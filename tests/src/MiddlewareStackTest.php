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

        $middlewares = $stack->getMiddlewares();

        $this->assertCount(2, $middlewares);
        $this->assertSame($middleware1, $middlewares[0]);
        $this->assertSame($middleware2, $middlewares[1]);
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

        $middlewares = $stack->getMiddlewares();

        $this->assertCount(2, $middlewares);
        $this->assertSame($middleware2, $middlewares[0], 'Prepended middleware should be first');
        $this->assertSame($middleware1, $middlewares[1], 'Original middleware should be second');
    }

    public function testFluentInterface(): void
    {
        $stack = new MiddlewareStack();
        $middleware = $this->createStub(MiddlewareInterface::class);

        $resultAdd = $stack->add($middleware);
        $resultPrepend = $stack->prepend($middleware);

        $this->assertSame($stack, $resultAdd);
        $this->assertSame($stack, $resultPrepend);
    }

    public function testCreateHandlerReturnsConfiguredRequestHandler(): void
    {
        $stack = new MiddlewareStack();
        $fallbackHandler = $this->createStub(RequestHandlerInterface::class);
        $middleware = $this->createStub(MiddlewareInterface::class);

        $stack->add($middleware);

        $handler = $stack->createHandler($fallbackHandler);

        // We verify that the factory method returns the correct concrete implementation
        $this->assertInstanceOf(RequestHandler::class, $handler);
    }
}
