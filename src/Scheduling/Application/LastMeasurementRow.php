<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use DateTimeImmutable;

final readonly class LastMeasurementRow
{
    public function __construct(
        public ConnectionId $connectionId,
        public ?DateTimeImmutable $completedAt,
        public ?string $serverId,
        public HealthHistory $healthHistory,
    ) {}
}
