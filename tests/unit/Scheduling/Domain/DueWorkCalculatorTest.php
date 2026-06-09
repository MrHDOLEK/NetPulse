<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain;

use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Scheduling\Domain\DueWorkCalculator;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use App\Scheduling\Infrastructure\Cron\DragonmantankCronEvaluator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DueWorkCalculatorTest extends TestCase
{
    private const string NOW = "2026-06-06 12:05:00";
    private const string SEED = "11111111-1111-7111-8111-111111111111";

    /**
     * @return iterable<string, array{Schedule, ServerPool, int|null, string|null, bool, string|null}>
     */
    public static function provideCases(): iterable
    {
        $pool = ServerPool::fromList("a", "b", "c");

        yield "cron due" => [Schedule::cron("* * * * *"), $pool, 300, "a", true, "b"];

        yield "cron not due" => [Schedule::cron("0 0 * * *"), $pool, 60, "a", false, null];

        yield "even due" => [Schedule::even(24, 0), $pool, 3600, "b", true, "c"];

        yield "even not due" => [Schedule::even(24, 0), $pool, 1800, "b", false, null];

        yield "first run due" => [Schedule::even(24, 0), $pool, null, null, true, "a"];

        yield "round robin next" => [Schedule::even(24, 0), $pool, 3600, "a", true, "b"];

        yield "round robin wrap" => [Schedule::even(24, 0), $pool, 3600, "c", true, "a"];

        yield "empty pool null server" => [Schedule::even(24, 0), ServerPool::empty(), null, null, true, null];
    }

    /**
     * @return iterable<string, array{HealthHistory, AdaptivePolicy, int, bool, string|null}>
     */
    public static function provideAdaptiveCases(): iterable
    {
        $policy = AdaptivePolicy::of(300, 3, 5);

        yield "degraded unhealthy newest densifies" => [
            self::history(self::unhealthy(0), self::healthy(1), self::healthy(2)),
            $policy,
            600,
            true,
            "b",
        ];

        yield "degraded but inside densified gate" => [
            self::history(self::unhealthy(0)),
            $policy,
            200,
            false,
            null,
        ];

        yield "healthy newest normal interval" => [
            self::history(self::healthy(0), self::unhealthy(1)),
            $policy,
            600,
            false,
            null,
        ];

        yield "recovery after N healthy" => [
            self::history(self::healthy(0), self::healthy(1), self::healthy(2), self::unhealthy(3), self::unhealthy(4)),
            $policy,
            600,
            false,
            null,
        ];

        yield "backoff after K failed" => [
            self::history(
                self::failed(0),
                self::failed(1),
                self::failed(2),
                self::failed(3),
                self::failed(4),
            ),
            $policy,
            600,
            false,
            null,
        ];

        yield "single failure densifies" => [
            self::history(self::failed(0), self::healthy(1)),
            $policy,
            600,
            true,
            "b",
        ];

        yield "below backoff threshold still densifies" => [
            self::history(self::failed(0), self::failed(1), self::failed(2), self::failed(3), self::healthy(4)),
            $policy,
            600,
            true,
            "b",
        ];

        yield "first run no history" => [
            HealthHistory::empty(),
            $policy,
            600,
            false,
            null,
        ];
    }

    /**
     * @param int|null $lastAtOffsetSeconds seconds before NOW for lastAt, or null for first-run
     * @param bool $expectedDue whether a DueDecision is expected (vs null)
     * @param string|null $expectedServerId server id the round-robin must pick (only when due)
     */
    #[DataProvider("provideCases")]
    public function testDecide(
        Schedule $schedule,
        ServerPool $pool,
        ?int $lastAtOffsetSeconds,
        ?string $lastServerId,
        bool $expectedDue,
        ?string $expectedServerId,
    ): void {
        $clock = new MockClock(self::NOW);
        $now = $clock->now();
        $lastAt = $lastAtOffsetSeconds === null
            ? null
            : $now->modify("-" . $lastAtOffsetSeconds . " seconds");

        $calculator = new DueWorkCalculator(new DragonmantankCronEvaluator());

        $decision = $calculator->decide(
            $schedule,
            $pool,
            $lastAt,
            $lastServerId,
            self::SEED,
            $now,
            AdaptivePolicy::default(),
            HealthHistory::empty(),
        );

        if (!$expectedDue) {
            self::assertNull($decision);

            return;
        }

        self::assertNotNull($decision);
        self::assertSame($expectedServerId, $decision->serverId);
    }

    /**
     * @param int $lastAtOffsetSeconds seconds before NOW for lastAt (always set; first-run is
     *                                 covered separately so this isolates the densified gate)
     */
    #[DataProvider("provideAdaptiveCases")]
    public function testDecideAdaptive(
        HealthHistory $history,
        AdaptivePolicy $policy,
        int $lastAtOffsetSeconds,
        bool $expectedDue,
        ?string $expectedServerId,
    ): void {
        $clock = new MockClock(self::NOW);
        $now = $clock->now();
        $lastAt = $now->modify("-" . $lastAtOffsetSeconds . " seconds");

        $schedule = Schedule::even(24, 0);
        $pool = ServerPool::fromList("a", "b", "c");

        $calculator = new DueWorkCalculator(new DragonmantankCronEvaluator());

        $decision = $calculator->decide($schedule, $pool, $lastAt, "a", self::SEED, $now, $policy, $history);

        if (!$expectedDue) {
            self::assertNull($decision);

            return;
        }

        self::assertNotNull($decision);

        self::assertSame($expectedServerId, $decision->serverId);
        self::assertNotSame("a", $decision->serverId);
    }

    public function testCronDegradedDensifiesBeforeNextTick(): void
    {
        $clock = new MockClock(self::NOW);
        $now = $clock->now();
        $lastAt = $now->modify("-600 seconds");

        $calculator = new DueWorkCalculator(new DragonmantankCronEvaluator());

        $schedule = Schedule::cron("0 0 * * *");
        $pool = ServerPool::fromList("a", "b");
        $policy = AdaptivePolicy::of(300, 3, 5);

        $healthy = $calculator->decide(
            $schedule,
            $pool,
            $lastAt,
            "a",
            self::SEED,
            $now,
            $policy,
            self::history(self::healthy(0)),
        );
        self::assertNull($healthy, "Healthy cron link is not due 600s after a daily-tick run.");

        $degraded = $calculator->decide(
            $schedule,
            $pool,
            $lastAt,
            "a",
            self::SEED,
            $now,
            $policy,
            self::history(self::unhealthy(0)),
        );
        self::assertNotNull($degraded, "Degraded cron link densifies and fires before the next tick.");
        self::assertSame("b", $degraded->serverId);
    }

    private static function history(HealthSample ...$samples): HealthHistory
    {
        return HealthHistory::of(...$samples);
    }

    private static function healthy(int $secondsAgoIndex): HealthSample
    {
        return HealthSample::completed(self::at($secondsAgoIndex), true);
    }

    private static function unhealthy(int $secondsAgoIndex): HealthSample
    {
        return HealthSample::completed(self::at($secondsAgoIndex), false);
    }

    private static function failed(int $secondsAgoIndex): HealthSample
    {
        return HealthSample::failed(self::at($secondsAgoIndex));
    }

    private static function at(int $secondsAgoIndex): DateTimeImmutable
    {
        return (new DateTimeImmutable(self::NOW))->modify("-" . ($secondsAgoIndex * 60 + 600) . " seconds");
    }
}
