<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\ConnectionStatus;

final readonly class ConnectionOverview
{
    public function __construct(
        public ConnectionId $connectionId,
        public string $name,
        public ConnectionColor $color,
        public string $isp,
        public ?int $downloadBits,
        public ?int $uploadBits,
        public ?float $pingSeconds,
        public ?float $jitterSeconds,
        public ?float $packetLossRatio,
        public ?int $completedAtUnix,
        public string $serverName,
        public string $serverLocation,
        public ?bool $latestHealthy,
        public ConnectionStatus $status,
        public int $testsRun,
        public int $incidents,
        public float $uptimePct,
    ) {}
}
