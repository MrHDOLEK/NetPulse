<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class LatestMeasurementRow
{
    public function __construct(
        public string $probeId,
        public string $probeName,
        public string $connectionId,
        public string $connectionName,
        public string $isp,
        public string $serverId,
        public string $serverName,
        public string $serverLocation,
        public string $site,
        public string $status,
        public int $completedAtUnix,
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $jitterSeconds,
        public ?float $packetLossRatio,
        public ?float $downloadLatencyIqmSeconds,
        public ?float $uploadLatencyIqmSeconds,
        public ?int $dataUsedBytes,
        public ?bool $healthy = null,
    ) {}
}
