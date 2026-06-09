<?php

declare(strict_types=1);

namespace App\Measurement\Domain\ValueObject;

final readonly class ServerInfo
{
    public function __construct(
        public string $serverId,
        public string $serverName,
        public string $serverLocation,
        public string $serverHost,
        public string $isp,
    ) {}
}
