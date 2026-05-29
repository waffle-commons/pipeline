[![PHP Version Require](http://poser.pugx.org/waffle-commons/pipeline/require/php)](https://packagist.org/packages/waffle-commons/pipeline)
[![PHP CI](https://github.com/waffle-commons/pipeline/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/pipeline/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/pipeline/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/pipeline)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/pipeline/v)](https://packagist.org/packages/waffle-commons/pipeline)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/pipeline/v/unstable)](https://packagist.org/packages/waffle-commons/pipeline)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/pipeline.svg)](https://packagist.org/packages/waffle-commons/pipeline)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/pipeline)](https://github.com/waffle-commons/pipeline/blob/main/LICENSE.md)

Waffle Pipeline Component
=========================

> **Release:** `v0.1.0-beta2` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **PSR Compliance:** PSR-15 (`Psr\Http\Server\MiddlewareInterface`, `RequestHandlerInterface`), PSR-17 (response factory, optional for `OPTIONS` auto-answer)

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
| `Waffle\Commons\Pipeline\CoreRoutingMiddleware` | Routes the request and exposes the resolved controller / route params on the request attributes. **Beta-1:** raises the contracts-side `RouteNotFoundException` (instead of a generic `RuntimeException`) when no route matches, so missing routes render as `404` rather than `500`. Accepts an optional PSR-17 `ResponseFactoryInterface` — when supplied, an `OPTIONS` request to a known path is auto-answered `204` + `Allow`. |
| `Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware` | `final readonly` PSR-15 middleware enforcing the configured trusted-host allowlist (RFC-003 §3.2). |
| `Waffle\Commons\Pipeline\Middleware\SecureHeadersMiddleware` | Adds baseline security response headers (`X-Content-Type-Options`, etc.). |

## 🚀 Building a stack (Beta-1 canonical order)

```php
use Waffle\Commons\Pipeline\MiddlewareStack;
use Waffle\Commons\Pipeline\CoreRoutingMiddleware;
use Waffle\Commons\Pipeline\Middleware\SecureHeadersMiddleware;
use Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware;
use Waffle\Commons\Security\Middleware\AnonymousSessionMiddleware;
use Waffle\Commons\Security\Middleware\CsrfMiddleware;
use Waffle\Commons\Security\Middleware\SecurityMiddleware;

$stack = new MiddlewareStack();

$stack
    ->add(new ErrorHandlerMiddleware($renderer, $logger))            // 1. outermost (catches everything)
    ->add(new TrustedHostMiddleware(['example.com', 'api.example.com']))
    ->add(new AnonymousSessionMiddleware())                          // 3. issues WAFFLE_SID + _anon_sid attr
    ->add(new CoreRoutingMiddleware($router, $responseFactory))      // 4. resolves _classname / _method; auto-answers OPTIONS
    ->add(new CsrfMiddleware($csrfTokenManager))                     // 5. validates #[RequiresCsrfToken] using _anon_sid
    ->add(new SecurityMiddleware($secureContainer, $logger))         // 6. fail-closed ABAC analysis
    ->add(new SecureHeadersMiddleware())                             // 7. innermost — defensive response headers
;

$handler = $stack->createHandler($controllerDispatcher);
$response = $handler->handle($serverRequest);
```

`AnonymousSessionMiddleware` must run before `CsrfMiddleware` — the CSRF HMAC binds to the SID it publishes. `CoreRoutingMiddleware` must run before both Csrf and Security — both read `_classname`/`_method` from its request attributes.

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

## 🧭 Architectural boundary (`mago guard`)

An active dependency **perimeter** is enforced on every CI run by `vendor/bin/mago guard` (bundled into `composer mago`; zero baselines). The rules live in [`mago.toml`](./mago.toml) under `[guard.perimeter]` — a forbidden `use` statement fails the build, not a reviewer.

Production code under `Waffle\Commons\Pipeline` may depend **only** on:

- `Waffle\Commons\Pipeline\**` — itself
- `Waffle\Commons\Contracts\**` — the shared contracts package, the **only** Waffle dependency permitted
- `Psr\**` — PSR interfaces (PSR-7 / PSR-15 / PSR-17)
- `@global` + `Psl\**` — PHP core and the PHP Standard Library

Test code under `WaffleTests\Commons\Pipeline` is unrestricted (`@all`). Structural rules are guarded too: interfaces must be named `*Interface`, `Exception\**` classes must end in `*Exception`, and any `Enum\**` namespace may hold only `enum` declarations.

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`, never directly through one another.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/pipeline waffle-dev composer tests
```

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
