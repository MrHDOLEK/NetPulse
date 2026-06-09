<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\SetProbeEnabled;

use App\Probe\Domain\ValueObject\ProbeId;

final readonly class ProbeEnabledSet
{
    public function __construct(
        public ProbeId $probeId,
        public bool $enabled,
    ) {}
}
