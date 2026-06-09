<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

use App\Connection\Domain\ValueObject\ConnectionId;

final readonly class MarkedConnection
{
    public function __construct(
        public ConnectionId $connectionId,
        public ?string $forcedServerId,
    ) {}
}
