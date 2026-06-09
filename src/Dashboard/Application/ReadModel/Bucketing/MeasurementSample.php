<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Bucketing;

final readonly class MeasurementSample
{
    public function __construct(
        public int $completedAtUnix,
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $packetLossRatio,
    ) {}
}
