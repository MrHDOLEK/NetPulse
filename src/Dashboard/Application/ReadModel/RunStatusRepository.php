<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\ValueObject\ConnectionId;

interface RunStatusRepository
{
    public function forConnection(ConnectionId $connectionId): RunStatus;
}
