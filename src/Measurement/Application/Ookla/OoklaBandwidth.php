<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

final readonly class OoklaBandwidth
{
    public function __construct(
        public ?int $bandwidth = null,
        public ?int $bytes = null,
        public ?int $elapsed = null,
        public ?OoklaLatency $latency = null,
    ) {}
}
