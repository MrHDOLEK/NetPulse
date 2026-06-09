<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Scheduling\Domain\ValueObject\HealthHistory;

final readonly class DegradationDecider
{
    public function isDegraded(HealthHistory $history, AdaptivePolicy $policy): bool
    {
        $newest = $history->newest();

        if ($newest === null) {
            return false;
        }

        if (!$newest->isUnhealthy()) {
            return false;
        }

        if ($history->newestAllHealthy($policy->recoveryHealthyCount())) {
            return false;
        }

        if ($history->newestAllFailed($policy->maxConsecutiveFailures())) {
            return false;
        }

        return true;
    }
}
