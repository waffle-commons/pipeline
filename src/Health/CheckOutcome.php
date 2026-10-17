<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Health;

use Waffle\Commons\Contracts\Health\Enum\HealthStatus;
use Waffle\Commons\Contracts\Health\HealthResult;

/**
 * One probe's result in a readiness report ([OPS-01]): what it answered and how long it took.
 */
final readonly class CheckOutcome
{
    /**
     * @param bool $critical Whether a failure makes the instance not ready: the probe says it is critical,
     *                       or the application lists it in `waffle.health.required`.
     */
    public function __construct(
        public string $name,
        public bool $critical,
        public HealthResult $result,
        public float $durationSeconds,
    ) {}

    public function isUp(): bool
    {
        return $this->result->status === HealthStatus::Up;
    }
}
