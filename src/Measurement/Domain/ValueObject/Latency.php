<?php

declare(strict_types=1);

namespace App\Measurement\Domain\ValueObject;

final readonly class Latency
{
    public function __construct(
        public float $ping,
        public float $pingLow,
        public float $pingHigh,
        public float $jitter,
        public float $downloadJitter,
        public float $uploadJitter,
        public float $downloadLatencyIqm,
        public float $downloadLatencyLow,
        public float $downloadLatencyHigh,
        public float $uploadLatencyIqm,
        public float $uploadLatencyLow,
        public float $uploadLatencyHigh,
    ) {}
}
