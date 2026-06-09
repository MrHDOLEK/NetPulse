<?php

declare(strict_types=1);

namespace App\Probe\Application\Command\CreateProbe;

use App\Shared\Domain\ValueObject\Labels;

final readonly class CreateProbeCommand
{
    public function __construct(
        public string $name,
        public Labels $labels,
    ) {}
}
