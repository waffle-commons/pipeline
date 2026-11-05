<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Pipeline\Health;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Health\Enum\HealthStatus;
use Waffle\Commons\Contracts\Health\HealthResult;
use Waffle\Commons\Pipeline\Health\CheckOutcome;
use Waffle\Commons\Pipeline\Health\ReadinessProbe;
use Waffle\Commons\Pipeline\Health\ReadinessReport;
use WaffleTests\Commons\Pipeline\AbstractTestCase;
use WaffleTests\Commons\Pipeline\Fixture\RecordingMetrics;
use WaffleTests\Commons\Pipeline\Fixture\StubHealthCheck;

#[CoversClass(ReadinessProbe::class)]
#[CoversClass(ReadinessReport::class)]
#[CoversClass(CheckOutcome::class)]
final class ReadinessProbeTest extends AbstractTestCase
{
    public function testReadyWhenEveryCriticalProbeIsUp(): void
    {
        $report = new ReadinessProbe([
            new StubHealthCheck('db.pool', HealthResult::up()),
            new StubHealthCheck('redis', HealthResult::up('pong')),
        ])->run();

        static::assertTrue($report->isReady());
        static::assertSame([], $report->missing);
        static::assertCount(2, $report->outcomes);
        static::assertSame('db.pool', self::outcome($report)->name);
        static::assertTrue(self::outcome($report)->critical);
        static::assertGreaterThanOrEqual(0.0, self::outcome($report)->durationSeconds);
    }

    public function testACriticalProbeDownMakesTheInstanceNotReady(): void
    {
        $report = new ReadinessProbe([
            new StubHealthCheck('db.pool', HealthResult::down('connection refused')),
        ])->run();

        static::assertFalse($report->isReady());
        static::assertFalse(self::outcome($report)->isUp());
    }

    public function testANonCriticalProbeDownLeavesTheInstanceReady(): void
    {
        $report = new ReadinessProbe([
            new StubHealthCheck('cache', HealthResult::down('timeout'), critical: false),
        ])->run();

        static::assertTrue($report->isReady());
    }

    public function testARequiredProbeCountsAsCritical(): void
    {
        $report = new ReadinessProbe([new StubHealthCheck('cache', HealthResult::down('timeout'), critical: false)], [
            'cache',
        ])->run();

        static::assertTrue(self::outcome($report)->critical);
        static::assertFalse($report->isReady());
    }

    public function testARequiredNameWithNoProbeMakesTheInstanceNotReady(): void
    {
        $report = new ReadinessProbe([new StubHealthCheck('db.pool', HealthResult::up())], ['db.pool', 'redis'])->run();

        static::assertSame(['redis'], $report->missing);
        static::assertFalse($report->isReady());
    }

    public function testAProbeThatThrowsCountsAsDown(): void
    {
        $report = new ReadinessProbe([
            new StubHealthCheck('redis', HealthResult::up(), throws: true),
        ])->run();

        static::assertFalse($report->isReady());
        static::assertSame(HealthStatus::Down, self::outcome($report)->result->status);
        static::assertStringContainsString('RuntimeException', self::outcome($report)->result->detail);
    }

    public function testExportsEachProbeDurationAndState(): void
    {
        $metrics = new RecordingMetrics();

        new ReadinessProbe(
            [
                new StubHealthCheck('db.pool', HealthResult::up()),
                new StubHealthCheck('redis', HealthResult::down('timeout')),
            ],
            [],
            $metrics,
        )->run();

        static::assertCount(4, $metrics->calls);
        static::assertSame(['observe', ReadinessProbe::DURATION_METRIC], [
            $metrics->calls[0][0],
            $metrics->calls[0][1],
        ]);
        static::assertSame(['check' => 'db.pool'], $metrics->calls[0][3]);
        static::assertSame(['gauge', ReadinessProbe::UP_METRIC, 1.0], array_slice($metrics->calls[1], 0, 3));
        static::assertSame(['gauge', ReadinessProbe::UP_METRIC, 0.0], array_slice($metrics->calls[3], 0, 3));
        static::assertSame(['check' => 'redis'], $metrics->calls[3][3]);
    }

    public function testNoProbeAndNothingRequiredIsReady(): void
    {
        static::assertTrue(new ReadinessProbe()->run()->isReady());
    }

    private static function outcome(ReadinessReport $report, int $index = 0): CheckOutcome
    {
        $outcome = $report->outcomes[$index] ?? null;
        if (!$outcome instanceof CheckOutcome) {
            static::fail('The report has no outcome #' . $index . '.');
        }

        return $outcome;
    }

    public function testTheDrainMarkerMakesTheInstanceNotReadyWithoutProbing(): void
    {
        $marker = dirname(__DIR__, 3) . '/var/drain-' . bin2hex(random_bytes(4));
        $probe = new ReadinessProbe(
            [new StubHealthCheck('redis', HealthResult::up(), throws: true)],
            ['redis'],
            null,
            $marker,
        );

        static::assertFalse($probe->run()->draining);

        touch($marker);
        try {
            $report = $probe->run();
        } finally {
            unlink($marker);
        }

        static::assertTrue($report->draining);
        static::assertFalse($report->isReady());
        static::assertSame([], $report->outcomes);
    }

    public function testRefusesTwoProbesWithTheSameName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReadinessProbe([
            new StubHealthCheck('redis', HealthResult::up()),
            new StubHealthCheck('redis', HealthResult::up()),
        ]);
    }
}
