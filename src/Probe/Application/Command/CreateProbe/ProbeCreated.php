<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\CreateProbe;

use App\Probe\Domain\ValueObject\ProbeId;

final readonly class ProbeCreated
{
    public function __construct(
        public ProbeId $probeId,
        public string $plaintextToken,
    ) {}
}
