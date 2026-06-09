<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;

final readonly class MeasurementDetail
{
    /**
     * @param array<string,mixed> $rawPayload
     */
    public function __construct(
        public MeasurementId $id,
        public int $completedAtUnix,
        public int $startedAtUnix,
        public string $connectionName,
        public ConnectionColor $connectionColor,
        public string $isp,
        public string $serverId,
        public string $serverName,
        public string $serverLocation,
        public string $serverHost,
        public bool $scheduled,
        public MeasurementStatus $status,
        public ?string $failReason,
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $pingLowSeconds,
        public ?float $pingHighSeconds,
        public ?float $jitterSeconds,
        public ?float $downloadLatencyIqmSeconds,
        public ?float $uploadLatencyIqmSeconds,
        public ?float $packetLossRatio,
        public ?bool $healthy,
        public ?int $dataUsedDownload,
        public ?int $dataUsedUpload,
        public ?string $resultUrl,
        public array $rawPayload,
    ) {}
}
