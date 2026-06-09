<?php

declare(strict_types=1);

namespace App\Agent\Application;

interface SpeedtestRunner
{
    /**
     * @param string|null $serverId pin the test to a specific Ookla server, or null to auto-pick
     */
    public function run(?string $serverId): SpeedtestOutcome;
}
