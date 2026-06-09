<?php

declare(strict_types=1);

namespace App\Scheduling\Application\GetDueWork;

use App\Probe\Domain\ValueObject\ProbeId;

final readonly class GetDueWorkQuery
{
    public function __construct(
        public ProbeId $probeId,
    ) {}
}
