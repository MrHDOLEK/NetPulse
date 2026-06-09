<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Application\RemoteWrite;

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
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Metrics\Application\RemoteWrite\MeasurementTimeSeriesMapper;
use App\Metrics\Application\RemoteWrite\PushMeasurementHandler;
use App\Metrics\Application\RemoteWrite\PushMeasurementMessage;
use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\Exception\RemoteWriteFailed;
use App\Metrics\Domain\RemoteWrite\RemoteWriteClient;
use App\Metrics\Domain\RemoteWriteFailureCounter;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeCollection;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

interface SpyFailureCounter extends RemoteWriteFailureCounter
{
    public function increments(): int;
}

final class PushMeasurementHandlerTest extends TestCase
{
    private const string MEASUREMENT_ID = "11111111-1111-4111-8111-111111111111";
    private const string PROBE_ID = "22222222-2222-4222-8222-222222222222";
    private const string CONNECTION_ID = "33333333-3333-4333-8333-333333333333";

    public function testWritesMappedSeriesThroughClient(): void
    {
        $repository = $this->repositoryReturning($this->completedMeasurement());

        $client = new class() implements RemoteWriteClient {
            public ?TimeSeriesCollection $written = null;

            public function write(TimeSeriesCollection $series): void
            {
                $this->written = $series;
            }
        };

        $counter = $this->spyCounter();

        $handler = new PushMeasurementHandler(
            $repository,
            $this->connectionRepository(),
            $this->probeRepository(),
            new MeasurementTimeSeriesMapper(""),
            $client,
            $counter,
            new NullLogger(),
        );

        $handler(new PushMeasurementMessage(self::MEASUREMENT_ID));

        self::assertNotNull($client->written);
        self::assertFalse($client->written->isEmpty());
        self::assertSame(0, $counter->increments());

        $labels = [];

        foreach ($client->written->toArray()[0]->labels as $label) {
            $labels[$label->name] = $label->value;
        }

        self::assertSame("home", $labels["probe"]);
        self::assertSame("wan1", $labels["connection"]);
    }

    public function testIncrementsCounterAndRethrowsOnFailure(): void
    {
        $repository = $this->repositoryReturning($this->completedMeasurement());

        $client = new class() implements RemoteWriteClient {
            public function write(TimeSeriesCollection $series): void
            {
                throw RemoteWriteFailed::withStatus(500, "boom");
            }
        };

        $counter = $this->spyCounter();

        $handler = new PushMeasurementHandler(
            $repository,
            $this->connectionRepository(),
            $this->probeRepository(),
            new MeasurementTimeSeriesMapper(""),
            $client,
            $counter,
            new NullLogger(),
        );

        try {
            $handler(new PushMeasurementMessage(self::MEASUREMENT_ID));
            self::fail("Expected RemoteWriteFailed to propagate for Messenger retry.");
        } catch (RemoteWriteFailed) {
        }

        self::assertSame(1, $counter->increments());
    }

    private function completedMeasurement(): Measurement
    {
        return MeasurementMother::fromOoklaArray(
            [
                "type" => "result",
                "timestamp" => "2026-06-05T10:00:01Z",
                "ping" => ["jitter" => 0.5, "latency" => 12.5, "low" => 11.0, "high" => 14.0],
                "download" => ["bandwidth" => 11_750_000, "bytes" => 50_000_000, "elapsed" => 5000],
                "upload" => ["bandwidth" => 2_000_000, "bytes" => 10_000_000, "elapsed" => 5000],
                "packetLoss" => 0.0,
                "server" => ["id" => 1, "name" => "S", "location" => "L", "host" => "h", "ip" => "1.2.3.4"],
                "result" => ["url" => "https://x"],
            ],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            (new MockClock("2026-06-06T10:00:00+00:00"))->now(),
        );
    }

    private function repositoryReturning(Measurement $measurement): MeasurementRepository
    {
        return new class($measurement) implements MeasurementRepository {
            public function __construct(
                private readonly Measurement $measurement,
            ) {}

            public function save(Measurement $measurement): void
            {
            }

            public function get(MeasurementId $id): Measurement
            {
                return $this->measurement;
            }

            public function find(MeasurementId $id): ?Measurement
            {
                return $this->measurement;
            }
        };
    }

    private function connectionRepository(): ConnectionRepository
    {
        $connection = new Connection(
            new ConnectionId(self::CONNECTION_ID),
            new ProbeId(self::PROBE_ID),
            "wan1",
            "Orange Polska",
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::fromList("12746"),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        return new class($connection) implements ConnectionRepository {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function save(Connection $connection): void
            {
            }

            public function delete(Connection $connection): void
            {
            }

            public function get(ConnectionId $connectionId): Connection
            {
                return $this->connection;
            }

            public function find(ConnectionId $connectionId): ?Connection
            {
                return $this->connection;
            }

            public function byProbe(ProbeId $probeId): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }

            public function allEnabled(): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }

            public function all(): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }
        };
    }

    private function probeRepository(): ProbeRepository
    {
        $probe = new Probe(
            new ProbeId(self::PROBE_ID),
            "home",
            Labels::fromArray(["site" => "warsaw"]),
            "hash",
            true,
            new DateTimeImmutable("2026-06-06T10:00:00+00:00"),
        );

        return new class($probe) implements ProbeRepository {
            public function __construct(
                private readonly Probe $probe,
            ) {}

            public function save(Probe $probe): void
            {
            }

            public function delete(Probe $probe): void
            {
            }

            public function get(ProbeId $id): Probe
            {
                return $this->probe;
            }

            public function find(ProbeId $id): ?Probe
            {
                return $this->probe;
            }

            public function all(): ProbeCollection
            {
                return ProbeCollection::of($this->probe);
            }
        };
    }

    private function spyCounter(): SpyFailureCounter
    {
        return new class() implements SpyFailureCounter {
            private int $count = 0;

            public function increment(): void
            {
                $this->count++;
            }

            public function total(): int
            {
                return $this->count;
            }

            public function increments(): int
            {
                return $this->count;
            }
        };
    }
}
