<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class ServerMetricsRow
{
    public function __construct(
        public string $serverId,
        public string $name,
        public string $location,
        public ?float $avgDownloadBits,
        public ?float $avgUploadBits,
        public ?float $avgPingSeconds,
        public ?float $avgLossRatio,
        public int $testCount,
        public int $healthyCount,
        public int $lastSeenUnix,
    ) {}
}
