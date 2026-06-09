<?php

declare(strict_types=1);

namespace App\Agent\Application;

interface NetPulseApiClient
{
    public function fetchDue(): DuePlan;

    /**
     * @param array<string,mixed> $ooklaJson the Ookla CLI JSON (success) or a minimal
     *                                       failed-shaped payload (see {@see SpeedtestOutcome::toOoklaJson()})
     */
    public function pushResult(string $connectionId, array $ooklaJson, bool $scheduled): void;
}
