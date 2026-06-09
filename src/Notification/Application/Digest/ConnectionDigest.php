<?php

declare(strict_types=1);

namespace App\Notification\Application\Digest;

final readonly class ConnectionDigest
{
    public function __construct(
        public string $probeName,
        public string $connectionName,
        public int $avgDownloadBits,
        public int $avgUploadBits,
        public float $avgPingMs,
        public float $avgPacketLossRatio,
        public float $healthyRatio,
        public int $testsCount,
        public int $failuresCount,
    ) {}
}
