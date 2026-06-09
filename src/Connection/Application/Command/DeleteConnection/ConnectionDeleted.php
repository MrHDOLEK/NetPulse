<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\DeleteConnection;

use App\Connection\Domain\ValueObject\ConnectionId;

final readonly class ConnectionDeleted
{
    public function __construct(
        public ConnectionId $connectionId,
    ) {}
}
