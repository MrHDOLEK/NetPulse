<?php

declare(strict_types=1);

namespace App\Connection\Domain;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\NotFoundException;

interface ConnectionRepository
{
    public function save(Connection $connection): void;

    public function delete(Connection $connection): void;

    /**
     * @throws NotFoundException
     */
    public function get(ConnectionId $connectionId): Connection;

    public function find(ConnectionId $connectionId): ?Connection;

    public function byProbe(ProbeId $probeId): ConnectionCollection;

    public function allEnabled(): ConnectionCollection;

    public function all(): ConnectionCollection;
}
