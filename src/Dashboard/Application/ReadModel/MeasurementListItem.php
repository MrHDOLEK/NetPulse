<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;

final readonly class MeasurementListItem
{
    public function __construct(
        public MeasurementId $id,
        public int $completedAtUnix,
        public MeasurementStatus $status,
        public string $connectionName,
        public ConnectionColor $connectionColor,
        public string $isp,
        public string $serverName,
        public string $serverLocation,
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $jitterSeconds,
        public ?float $packetLossRatio,
        public ?bool $healthy,
        public bool $scheduled,
    ) {}
}
