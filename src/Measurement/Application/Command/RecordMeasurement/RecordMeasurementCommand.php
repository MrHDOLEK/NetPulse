<?php

declare(strict_types=1);

namespace App\Measurement\Application\Command\RecordMeasurement;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Application\Ookla\OoklaResult;
use App\Probe\Domain\ValueObject\ProbeId;

final readonly class RecordMeasurementCommand
{
    /**
     * @param array<string,mixed> $rawPayload
     */
    public function __construct(
        public ProbeId $probeId,
        public ConnectionId $connectionId,
        public OoklaResult $ookla,
        public bool $scheduled,
        public array $rawPayload,
    ) {}
}
