<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Entity;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\RunPhase;
use DateTimeImmutable;

class RunState
{
    public function __construct(
        private ConnectionId $connectionId,
        private RunPhase $phase,
        private DateTimeImmutable $updatedAt,
    ) {}

    public function connectionId(): ConnectionId
    {
        return $this->connectionId;
    }

    public function phase(): RunPhase
    {
        return $this->phase;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
