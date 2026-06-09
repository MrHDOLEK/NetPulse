<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Entity;

use App\Connection\Domain\ValueObject\ConnectionId;
use DateTimeImmutable;

class DueNowMarker
{
    public function __construct(
        private ConnectionId $connectionId,
        private DateTimeImmutable $requestedAt,
        private ?string $forcedServerId = null,
    ) {}

    public function connectionId(): ConnectionId
    {
        return $this->connectionId;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function forcedServerId(): ?string
    {
        return $this->forcedServerId;
    }
}
