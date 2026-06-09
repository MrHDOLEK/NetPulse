<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\DeleteConnection;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;

final readonly class DeleteConnectionCommand
{
    public function __construct(
        public ConnectionId $connectionId,
        public ProbeId $probeId,
    ) {}
}
