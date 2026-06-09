<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\RunPhase;
use DateTimeImmutable;

interface RunStateRepository
{
    public function upsert(ConnectionId $connectionId, RunPhase $phase, DateTimeImmutable $at): void;

    public function markDoneIfPending(ConnectionId $connectionId, DateTimeImmutable $at): void;
}
