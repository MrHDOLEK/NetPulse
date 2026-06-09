<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;

interface OoklaResultMapper
{
    /**
     * @param array<string,mixed> $rawPayload
     */
    public function toMeasurement(
        MeasurementId $id,
        ProbeId $probeId,
        ConnectionId $connectionId,
        OoklaResult $result,
        bool $scheduled,
        DateTimeImmutable $recordedAt,
        array $rawPayload,
    ): Measurement;
}
