# Changelog — waffle-commons/pipeline

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [Unreleased]

### Added
- **Orchestrator probes (OPS-01, RFC-023).** `Middleware\ProbeMiddleware` serves `/healthz` (liveness: 200 while the worker can serve a request; it never checks a dependency) and `/readyz` (readiness: 200 when ready, 503 otherwise), with a minimal public body. The per-probe report goes only to the bearer token or an allow-listed `REMOTE_ADDR`, the access rule of `/waffle-metrics`. Mount it before `TrustedHostMiddleware`: kubelet probes carry the pod IP as `Host` (RFC-023 §5.2).
- `Health\ReadinessProbe` runs the registered `HealthCheckInterface` probes fail-closed: a required name with no probe, or a critical probe down, means not ready; a probe listed in `waffle.health.required` counts as critical; a probe that throws counts as down. It times every probe and exports `waffle_health_check_duration_seconds` and `waffle_health_check_up` (RFC-023 §5.4). Two probes with the same name are refused at construction.

### Dependencies
- `psr/http-factory` and `psr/http-message` are direct requirements; `nyholm/psr7` is a development dependency for the component's own tests.

## [0.1.0-beta6] — 2026-09

**Theme: documentation.**

### Documentation
- The README now links into the central Diátaxis documentation tree (DOC-02).

## [0.1.0-beta5] — 2026-07-08

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
