<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\DeleteProbe;

use App\Probe\Domain\ValueObject\ProbeId;

final readonly class DeleteProbeCommand
{
    public function __construct(
        public ProbeId $probeId,
    ) {}
}
