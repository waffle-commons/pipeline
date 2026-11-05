<?php

declare(strict_types=1);

namespace Waffle\Commons\Pipeline\Health;

/**
 * The outcome of one `/readyz` evaluation ([OPS-01], RFC-023 §3.2).
 */
final readonly class ReadinessReport
{
    /**
     * @param list<CheckOutcome> $outcomes Every registered probe, in registration order.
     * @param list<string>       $missing  Names listed in `waffle.health.required` that no probe answers to.
     * @param bool               $draining The drain marker exists: the pod is terminating (RFC-023 §5.1).
     */
    public function __construct(
        public array $outcomes,
        public array $missing,
        public bool $draining = false,
    ) {}

    /**
     * Fail-closed: ready only when the pod is not draining, no required probe is missing and every critical
     * probe is up.
     */
    public function isReady(): bool
    {
        if ($this->draining || $this->missing !== []) {
            return false;
        }

        foreach ($this->outcomes as $outcome) {
            if ($outcome->critical && !$outcome->isUp()) {
                return false;
            }
        }

        return true;
    }
}
