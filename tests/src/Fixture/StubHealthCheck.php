<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Fixture;

use RuntimeException;
use Waffle\Commons\Contracts\Health\HealthCheckInterface;
use Waffle\Commons\Contracts\Health\HealthResult;

/**
 * A probe with a fixed answer; `$throws` makes it break the contract and throw.
 */
final readonly class StubHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private string $name,
        private HealthResult $result,
        private bool $critical = true,
        private bool $throws = false,
    ) {}

    #[\Override]
    public function name(): string
    {
        return $this->name;
    }

    #[\Override]
    public function isCritical(): bool
    {
        return $this->critical;
    }

    #[\Override]
    public function check(): HealthResult
    {
        if ($this->throws) {
            throw new RuntimeException('connection refused');
        }

        return $this->result;
    }
}
