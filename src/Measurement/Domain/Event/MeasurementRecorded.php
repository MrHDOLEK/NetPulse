<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Event;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;

final readonly class MeasurementRecorded
{
    public function __construct(
        public MeasurementId $measurementId,
        public ProbeId $probeId,
        public ConnectionId $connectionId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
