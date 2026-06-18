<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Application;

use App\Agent\Application\DuePlan;
use App\Agent\Application\NetPulseApiClient;

final class RecordingNetPulseApiClient implements NetPulseApiClient
{
    /** @var list<array{connectionId: string, ookla: array<string,mixed>, scheduled: bool}> */
    public array $pushed = [];

    public function __construct(
        private readonly DuePlan $plan,
    ) {}

    public function fetchDue(): DuePlan
    {
        return $this->plan;
    }

    public function pushResult(string $connectionId, array $ooklaJson, bool $scheduled): void
    {
        $this->pushed[] = [
            'connectionId' => $connectionId,
            'ookla' => $ooklaJson,
            'scheduled' => $scheduled,
        ];
    }
}
