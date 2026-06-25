# Changelog — waffle-commons/pipeline

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta5] — 2026-06-26

**Theme: static-analysis hardening.**

### Changed
- `mago.toml`: enabled the `cyclomatic-complexity` linter rule with `threshold = 50` (previously disabled). Config-only — no source or behavioural changes in this component; the threshold ratchets future per-function complexity without flagging the current pipeline code.

## [0.1.0-beta4] — 2026-06-13

### Changed
- Lockstep version bump with the Beta-4 wave. No behavioural changes in this component since `0.1.0-beta3`; constructor-injected `Router` (ARCH-02) and the typed `405`/`OPTIONS` handling remain as shipped.

## [0.1.0-beta3] — 2026-06-07

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Changed
- `MiddlewareStack` boot-time vs request-time behaviour documented inline (worker-safety clarity; no behavioural change).
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

## [0.1.0-beta2.1] — 2026-05-30

### Changed
- Lockstep re-tag of `0.1.0-beta2` (umbrella housekeeping patch) — no source changes in this component.

## [0.1.0-beta2] — 2026-05-29

**Theme: HTTP correctness — `OPTIONS` preflight auto-response and `405` propagation.**

### Added
- `CoreRoutingMiddleware` constructor accepts an optional PSR-17 `Psr\Http\Message\ResponseFactoryInterface`. When wired, the middleware intercepts `Waffle\Commons\Contracts\Routing\Exception\MethodNotAllowedExceptionInterface` raised on an `OPTIONS` request to a known path and synthesises a **`204 No Content` response with an `Allow` header** — no controller dispatch required.
- README documents the OPTIONS auto-answer behaviour and the updated middleware-stack wiring snippet showing the new `$responseFactory` parameter.

### Changed
- `CoreRoutingMiddleware` catch block now includes `MethodNotAllowedExceptionInterface` alongside the existing `RouteNotFoundExceptionInterface`. Both bubble naturally to `ErrorHandlerMiddleware` as HTTP 405 / 404 respectively when no auto-response handler applies.

### Tests
- `testRespondsToOptionsRequestForKnownPath` — verifies the `204 + Allow` auto-response.
- `test405PropagatesWhenNoResponseFactoryWired` — verifies graceful degradation when the optional factory is absent.
- `testNonOptionsRequestsPropagateNormally` — verifies that GET/POST/etc. requests still surface 405 to the error handler.

### Dependencies
- `composer.lock` refreshed.

## [0.1.0-beta1]

See the umbrella [CHANGELOG](../CHANGELOG.md#010-beta1) for the full Beta-1 narrative.
