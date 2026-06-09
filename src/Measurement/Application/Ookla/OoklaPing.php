<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

final readonly class OoklaPing
{
    public function __construct(
        public ?float $latency = null,
        public ?float $low = null,
        public ?float $high = null,
        public ?float $jitter = null,
    ) {}
}
