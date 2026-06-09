<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\RotateProbeToken;

use App\Probe\Domain\ValueObject\ProbeId;

final readonly class RotateProbeTokenCommand
{
    public function __construct(
        public ProbeId $probeId,
    ) {}
}
