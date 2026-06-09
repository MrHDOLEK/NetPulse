<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Enum\MeasurementStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MeasurementFilter
{
    public function __construct(
        public ?ConnectionId $connection,
        public DateTimeImmutable $since,
        public DateTimeImmutable $until,
        public ?string $serverId,
        public ?MeasurementStatus $status,
        public ?bool $healthy,
        public ?bool $scheduled,
    ) {
        if ($since > $until) {
            throw new InvalidArgumentException("MeasurementFilter window runs backwards: since must be <= until.");
        }
    }

    public static function lastDays(
        int $days,
        DateTimeImmutable $now,
        ?ConnectionId $connection,
        ?string $serverId,
        ?MeasurementStatus $status,
        ?bool $healthy,
        ?bool $scheduled,
    ): self {
        return new self(
            connection: $connection,
            since: $now->modify("-{$days} days"),
            until: $now,
            serverId: $serverId,
            status: $status,
            healthy: $healthy,
            scheduled: $scheduled,
        );
    }
}
