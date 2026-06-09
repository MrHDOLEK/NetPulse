<?php

declare(strict_types=1);

namespace App\Measurement\Application\PublicResult;

use App\Measurement\Domain\Enum\MeasurementStatus;

final readonly class PublicResult
{
    public function __construct(
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $jitterSeconds,
        public ?float $lossRatio,
        public string $serverName,
        public string $serverLocation,
        public string $isp,
        public int $completedAtUnix,
        public MeasurementStatus $status,
        public ?bool $healthy,
    ) {}
}
