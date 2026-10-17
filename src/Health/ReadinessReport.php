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
     */
    public function __construct(
        public array $outcomes,
        public array $missing,
    ) {}

    /**
     * Fail-closed: ready only when no required probe is missing and every critical probe is up.
     */
    public function isReady(): bool
    {
        if ($this->missing !== []) {
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
