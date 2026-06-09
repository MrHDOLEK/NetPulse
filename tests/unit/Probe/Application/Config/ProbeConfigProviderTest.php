<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Application\Config;

use App\Connection\Domain\ConnectionCollection;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Application\Config\ProbeConfigProvider;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProbeConfigProviderTest extends TestCase
{
    private const string PROBE_ID = "22222222-2222-4222-8222-222222222222";
    private const string CONNECTION_ID = "33333333-3333-4333-8333-333333333333";

    public function testBuildsConfigWithProbeStateAndConnectionList(): void
    {
        $probe = new Probe(
            new ProbeId(self::PROBE_ID),
            "home",
            Labels::empty(),
            "hash",
            true,
            new DateTimeImmutable(),
        );

        $provider = new ProbeConfigProvider($this->connectionsFor($probe->id()));

        $config = $provider->forProbe($probe);

        $this->assertSame([
            "probe" => [
                "id" => self::PROBE_ID,
                "enabled" => true,
            ],
            "connections" => [
                [
                    "id" => self::CONNECTION_ID,
                    "name" => "wan1",
                    "labels" => ["link" => "wan1"],
                    "serverPool" => ["12746"],
                    "enabled" => true,
                ],
            ],
        ], $config->toArray());
    }

    public function testDisabledProbeWithNoConnectionsYieldsEmptyConnectionList(): void
    {
        $probe = new Probe(
            new ProbeId(self::PROBE_ID),
            "home",
            Labels::empty(),
            "hash",
            false,
            new DateTimeImmutable(),
        );

        $provider = new ProbeConfigProvider($this->emptyConnections());

        $config = $provider->forProbe($probe);

        $this->assertFalse($config->probeEnabled);
        $this->assertSame([], $config->toArray()["connections"]);
    }

    private function connectionsFor(ProbeId $owner): ConnectionRepository
    {
        $connection = new Connection(
            new ConnectionId(self::CONNECTION_ID),
            $owner,
            "wan1",
            "Orange Polska",
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::fromArray(["link" => "wan1"]),
            ServerPool::fromList("12746"),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        return $this->repository(ConnectionCollection::of($connection));
    }

    private function emptyConnections(): ConnectionRepository
    {
        return $this->repository(ConnectionCollection::of());
    }

    private function repository(ConnectionCollection $collection): ConnectionRepository
    {
        return new class($collection) implements ConnectionRepository {
            public function __construct(
                private readonly ConnectionCollection $collection,
            ) {}

            public function save(Connection $connection): void
            {
            }

            public function delete(Connection $connection): void
            {
            }

            public function get(ConnectionId $connectionId): Connection
            {
                return $this->collection->toArray()[0];
            }

            public function find(ConnectionId $connectionId): ?Connection
            {
                return $this->collection->toArray()[0] ?? null;
            }

            public function byProbe(ProbeId $probeId): ConnectionCollection
            {
                return $this->collection;
            }

            public function allEnabled(): ConnectionCollection
            {
                return $this->collection;
            }

            public function all(): ConnectionCollection
            {
                return $this->collection;
            }
        };
    }
}
