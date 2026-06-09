<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Notification\Domain\ValueObject\AlertDecision;
use App\Scheduling\Domain\ValueObject\HealthHistory;

use function array_slice;

final readonly class AlertDecider
{
    public function decide(HealthHistory $history, int $threshold): AlertDecision
    {
        if ($threshold < 1) {
            return AlertDecision::none();
        }

        $newest = $history->newest();

        if ($newest === null) {
            return AlertDecision::none();
        }

        if ($newest->isHealthy()) {
            $precedingUnhealthy = $this->leadingUnhealthyCountFrom($history, 1);

            if ($precedingUnhealthy >= $threshold) {
                return AlertDecision::recovery(
                    "recovered after {$precedingUnhealthy} consecutive unhealthy measurements",
                );
            }

            return AlertDecision::none();
        }

        if ($history->leadingUnhealthyCount() === $threshold) {
            return AlertDecision::alert("{$threshold} consecutive unhealthy measurements");
        }

        return AlertDecision::none();
    }

    private function leadingUnhealthyCountFrom(HealthHistory $history, int $offset): int
    {
        $count = 0;

        foreach (array_slice($history->toArray(), $offset) as $sample) {
            if (!$sample->isUnhealthy()) {
                break;
            }

            $count++;
        }

        return $count;
    }
}
