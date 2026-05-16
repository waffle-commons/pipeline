[![PHP Version Require](http://poser.pugx.org/waffle-commons/pipeline/require/php)](https://packagist.org/packages/waffle-commons/pipeline)
[![PHP CI](https://github.com/waffle-commons/pipeline/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/pipeline/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/pipeline/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/pipeline)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/pipeline/v)](https://packagist.org/packages/waffle-commons/pipeline)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/pipeline/v/unstable)](https://packagist.org/packages/waffle-commons/pipeline)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/pipeline.svg)](https://packagist.org/packages/waffle-commons/pipeline)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/pipeline)](https://github.com/waffle-commons/pipeline/blob/main/LICENSE.md)

Waffle Pipeline Component
=========================

> **Release:** `v0.1.0-beta0`
> **PSR Compliance:** PSR-15 (`Psr\Http\Server\MiddlewareInterface`, `RequestHandlerInterface`)

The PSR-15 middleware stack that runs every request through the kernel. The stack locks itself the moment a request enters it, so middleware order cannot be tampered with mid-request.

## 📦 Installation

```bash
composer require waffle-commons/pipeline
```

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\Pipeline\MiddlewareStack` | `final` registry of middleware (`add`, `prepend`, `getMiddlewares`, `createHandler`). Implements `MiddlewareStackInterface`. |
| `Waffle\Commons\Pipeline\RequestHandler` | The PSR-15 handler that walks the stack and falls through to a terminal handler. |
| `Waffle\Commons\Pipeline\CoreRoutingMiddleware` | Routes the request and exposes the resolved controller / route params on the request attributes. |
| `Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware` | `final readonly` PSR-15 middleware enforcing the configured trusted-host allowlist (RFC-003 §3.2). |
| `Waffle\Commons\Pipeline\Middleware\SecureHeadersMiddleware` | Adds baseline security response headers (`X-Content-Type-Options`, etc.). |

## 🚀 Building a stack

```php
use Waffle\Commons\Pipeline\MiddlewareStack;
use Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware;
use Waffle\Commons\Pipeline\Middleware\SecureHeadersMiddleware;

$stack = new MiddlewareStack();

$stack
    ->add(new ErrorHandlerMiddleware($renderer, $logger))         // outermost
    ->add(new TrustedHostMiddleware(['example.com', 'api.example.com']))
    ->add(new SecureHeadersMiddleware())
    ->add(new CoreRoutingMiddleware($router))
    ->add(new SecurityMiddleware($security))                       // innermost
;

$handler = $stack->createHandler($controllerDispatcher);
$response = $handler->handle($serverRequest);
```

After `createHandler()`, the stack is locked. Further `add()` / `prepend()` calls raise `RuntimeException('MiddlewareStack is locked and cannot be modified during request processing.')`.

## 🔒 Locking semantics

```php
final class MiddlewareStack implements MiddlewareStackInterface
{
    public private(set) array $middlewares = [];   // PHP 8.5 asymmetric visibility

    public function add(MiddlewareInterface $middleware): static;       // fluent
    public function prepend(MiddlewareInterface $middleware): static;   // fluent
    public function getMiddlewares(): array;
    public function createHandler(RequestHandlerInterface $fallback): RequestHandlerInterface;
}
```

`public private(set)` on `$middlewares` exposes the array for read-only inspection (tests, debug pages) while keeping mutation strictly through `add()` / `prepend()`.

## 🛡️ Trusted-host middleware

`TrustedHostMiddleware` is `final readonly` and takes a list of trusted hosts; matching is case-insensitive against `UriInterface::getHost()`. An empty list disables the check (DEV-only convenience). The middleware throws `\InvalidArgumentException` on missing or untrusted Host, which `ErrorHandlerMiddleware` converts to RFC 7807 `HTTP 400`.

## 🐘 PHP 8.5 features used

- **Asymmetric visibility** (`public private(set) array $middlewares`).
- **`final readonly` middleware classes** so mounting them is side-effect-free.
- **`#[\Override]`** on every PSR-15 implementation method.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/pipeline waffle-dev composer tests
```

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
