<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Probe\Domain\ValueObject\ProbeId;

interface LastMeasurementRepository
{
    public function forProbe(ProbeId $probeId): LastMeasurementRowCollection;
}
