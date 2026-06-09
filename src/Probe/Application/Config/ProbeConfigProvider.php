<?php

declare(strict_types=1);

namespace App\Probe\Application\Config;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Probe\Domain\Entity\Probe;

final readonly class ProbeConfigProvider
{
    public function __construct(
        private ConnectionRepository $connections,
    ) {}

    public function forProbe(Probe $probe): ProbeConfig
    {
        $connections = array_map(
            static fn(Connection $connection): ProbeConnectionConfig => new ProbeConnectionConfig(
                id: $connection->id()->toString(),
                name: $connection->name(),
                labels: $connection->labels()->all(),
                serverPool: $connection->serverPool()->all(),
                enabled: $connection->isEnabled(),
            ),
            $this->connections->byProbe($probe->id())->toArray(),
        );

        return new ProbeConfig(
            probeId: $probe->id()->toString(),
            probeEnabled: $probe->isEnabled(),
            connections: $connections,
        );
    }
}
