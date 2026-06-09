<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;

interface DueNowMarkerRepository
{
    public function mark(ConnectionId $connectionId, DateTimeImmutable $requestedAt, ?string $forcedServerId): void;

    public function pullForProbe(ProbeId $probeId): MarkedConnectionCollection;
}
