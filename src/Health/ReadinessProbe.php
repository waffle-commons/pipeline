<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Health;

use InvalidArgumentException;
use Throwable;
use Waffle\Commons\Contracts\Health\HealthCheckInterface;
use Waffle\Commons\Contracts\Health\HealthResult;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;

/**
 * Runs the registered probes for `/readyz` ([OPS-01], RFC-023 §3.2).
 *
 * Each probe is timed here, not by the probe itself, and the durations go to `/waffle-metrics`
 * (RFC-023 §5.4). A probe listed in `waffle.health.required` counts as critical, and a required name with
 * no probe makes the instance not ready. Nothing is cached between two calls: every `/readyz` asks again.
 */
final readonly class ReadinessProbe
{
    public const string DURATION_METRIC = 'waffle_health_check_duration_seconds';

    public const string UP_METRIC = 'waffle_health_check_up';

    /**
     * @param list<HealthCheckInterface> $checks   The registered probes; their names must be unique.
     * @param list<string>               $required The names in `waffle.health.required`.
     *
     * @throws InvalidArgumentException When two probes share a name.
     */
    public function __construct(
        private array $checks = [],
        private array $required = [],
        private ?MetricsRegistryInterface $metrics = null,
    ) {
        $seen = [];
        foreach ($checks as $check) {
            $name = $check->name();
            if (array_key_exists($name, $seen)) {
                throw new InvalidArgumentException('Two health checks are both named "' . $name . '".');
            }

            $seen[$name] = true;
        }
    }

    public function run(): ReadinessReport
    {
        $outcomes = [];
        $registered = [];

        foreach ($this->checks as $check) {
            $name = $check->name();
            $registered[$name] = true;

            $started = hrtime(true);
            $result = self::probe($check);
            $seconds = (hrtime(true) - $started) / 1_000_000_000;

            $outcome = new CheckOutcome(
                $name,
                $check->isCritical() || in_array($name, $this->required, true),
                $result,
                $seconds,
            );
            $outcomes[] = $outcome;

            $this->metrics?->observe(self::DURATION_METRIC, $seconds, ['check' => $name]);
            $this->metrics?->gauge(self::UP_METRIC, $outcome->isUp() ? 1.0 : 0.0, ['check' => $name]);
        }

        $missing = [];
        foreach ($this->required as $name) {
            if (array_key_exists($name, $registered)) {
                continue;
            }

            $missing[] = $name;
        }

        return new ReadinessReport($outcomes, $missing);
    }

    private static function probe(HealthCheckInterface $check): HealthResult
    {
        try {
            return $check->check();
        } catch (Throwable $e) {
            // The contract forbids throwing; a probe that throws anyway counts as down (fail-closed).
            return HealthResult::down('The probe threw ' . $e::class . '.');
        }
    }
}
