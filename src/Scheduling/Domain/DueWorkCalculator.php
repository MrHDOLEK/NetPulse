<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Scheduling\Domain\ValueObject\DueDecision;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use DateTimeImmutable;

use function array_search;
use function count;
use function crc32;
use function intdiv;
use function max;
use function min;

final readonly class DueWorkCalculator
{
    private const int SECONDS_PER_DAY = 86400;

    public function __construct(
        private CronEvaluator $cron,
        private DegradationDecider $degradation = new DegradationDecider(),
    ) {}

    public function decide(
        Schedule $schedule,
        ServerPool $pool,
        ?DateTimeImmutable $lastAt,
        ?string $lastServerId,
        string $seed,
        DateTimeImmutable $now,
        AdaptivePolicy $policy,
        HealthHistory $history,
    ): ?DueDecision {
        if ($this->isDue($schedule, $lastAt, $seed, $now, $policy, $history)) {
            return DueDecision::due($this->nextServerId($pool, $lastServerId));
        }

        return null;
    }

    public function forcedDue(ServerPool $pool, ?string $lastServerId): DueDecision
    {
        return DueDecision::due($this->nextServerId($pool, $lastServerId));
    }

    private function isDue(
        Schedule $schedule,
        ?DateTimeImmutable $lastAt,
        string $seed,
        DateTimeImmutable $now,
        AdaptivePolicy $policy,
        HealthHistory $history,
    ): bool {
        if ($lastAt === null) {
            return true;
        }

        $degraded = $this->degradation->isDegraded($history, $policy);

        if ($degraded) {
            $effective = min($this->normalIntervalSeconds($schedule, $seed), $policy->adaptiveIntervalSeconds());

            return $now->getTimestamp() >= $lastAt->getTimestamp() + $effective;
        }

        return match ($schedule->mode()) {
            ScheduleMode::Cron => $this->isCronDue($schedule, $lastAt, $now),
            ScheduleMode::Even => $this->isEvenDue($schedule, $lastAt, $seed, $now),
        };
    }

    private function normalIntervalSeconds(Schedule $schedule, string $seed): int
    {
        return match ($schedule->mode()) {
            ScheduleMode::Even => $this->evenSlotSeconds($schedule, $seed),
            ScheduleMode::Cron => self::SECONDS_PER_DAY,
        };
    }

    private function evenSlotSeconds(Schedule $schedule, string $seed): int
    {
        $slot = intdiv(self::SECONDS_PER_DAY, max(1, $schedule->testsPerDay()));

        return $slot + $this->jitter($schedule->jitterSeconds(), $seed);
    }

    private function isCronDue(Schedule $schedule, ?DateTimeImmutable $lastAt, DateTimeImmutable $now): bool
    {
        if ($lastAt === null) {
            return true;
        }

        foreach ($schedule->cronExpressions() as $expression) {
            if ($this->cron->matchesSince($expression, $lastAt, $now)) {
                return true;
            }
        }

        return false;
    }

    private function isEvenDue(Schedule $schedule, ?DateTimeImmutable $lastAt, string $seed, DateTimeImmutable $now): bool
    {
        if ($lastAt === null) {
            return true;
        }

        return $now->getTimestamp() >= $lastAt->getTimestamp() + $this->evenSlotSeconds($schedule, $seed);
    }

    private function jitter(int $jitterSeconds, string $seed): int
    {
        if ($jitterSeconds === 0) {
            return 0;
        }

        return (int)(crc32($seed) % ($jitterSeconds + 1));
    }

    private function nextServerId(ServerPool $pool, ?string $lastServerId): ?string
    {
        $servers = $pool->all();
        $count = count($servers);

        if ($count === 0) {
            return null;
        }

        $index = $lastServerId === null ? -1 : array_search($lastServerId, $servers, true);

        if ($index === false) {
            $index = -1;
        }

        return $servers[($index + 1) % $count];
    }
}
