<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Fixture;

use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;

/**
 * Records every metric call, so a test can assert what was exported.
 */
final class RecordingMetrics implements MetricsRegistryInterface
{
    /** @var list<array{string, string, float, array<string, string>}> */
    public private(set) array $calls = [];

    /** @param array<string, string> $labels */
    #[\Override]
    public function increment(string $name, float $value = 1.0, array $labels = []): void
    {
        $this->calls[] = ['increment', $name, $value, $labels];
    }

    /** @param array<string, string> $labels */
    #[\Override]
    public function observe(string $name, float $value, array $labels = []): void
    {
        $this->calls[] = ['observe', $name, $value, $labels];
    }

    /** @param array<string, string> $labels */
    #[\Override]
    public function gauge(string $name, float $value, array $labels = []): void
    {
        $this->calls[] = ['gauge', $name, $value, $labels];
    }
}
