<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use DateTimeImmutable;

final readonly class SeriesBucket
{
    public function __construct(
        public DateTimeImmutable $bucketStart,
        public ?int $downloadBits = null,
        public ?int $uploadBits = null,
        public ?float $pingSeconds = null,
        public ?float $packetLossRatio = null,
    ) {}
}
