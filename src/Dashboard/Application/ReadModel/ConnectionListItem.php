<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\ConnectionId;

final readonly class ConnectionListItem
{
    public function __construct(
        public ConnectionId $connectionId,
        public string $name,
        public string $isp,
        public ConnectionColor $color,
        public int $expectedDownloadBits,
        public string $probeName,
    ) {}
}
