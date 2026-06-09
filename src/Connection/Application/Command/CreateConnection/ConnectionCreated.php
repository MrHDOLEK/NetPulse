<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\CreateConnection;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;

final readonly class ConnectionCreated
{
    public function __construct(
        public ConnectionId $connectionId,
        public ProbeId $probeId,
    ) {}
}
