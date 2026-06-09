<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\SetConnectionEnabled;

use App\Connection\Domain\ValueObject\ConnectionId;

final readonly class ConnectionEnabledSet
{
    public function __construct(
        public ConnectionId $connectionId,
        public bool $enabled,
    ) {}
}
