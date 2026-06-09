<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Application\Command\RecordMeasurement;

use App\Connection\Domain\ConnectionCollection;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Exception\ConnectionNotOwnedByProbe;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Measurement\Application\Command\RecordMeasurement\RecordMeasurementCommand;
use App\Measurement\Application\Command\RecordMeasurement\RecordMeasurementHandler;
use App\Measurement\Application\Ookla\DefaultOoklaResultMapper;
use App\Measurement\Application\Ookla\OoklaResult;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\Service\HealthEvaluator;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Application\Service\IdGeneratorInterface;
use App\Shared\Domain\Id;
use App\Shared\Domain\ValueObject\Labels;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RecordMeasurementHandlerTest extends TestCase
{
    private const PROBE_ID = "22222222-2222-4222-8222-222222222222";
    private const CONNECTION_ID = "33333333-3333-4333-8333-333333333333";
    private const MEASUREMENT_ID = "99999999-9999-4999-8999-999999999999";
    private const RECORDED_AT = "2026-06-06T10:00:00+00:00";

    public function testRecordsMeasurementAndDispatchesEvent(): void
    {
        $repository = $this->repository();
        $eventBus = $this->eventBus();

        $handler = new RecordMeasurementHandler(
            $repository,
            $this->connectionRepository(new ProbeId(self::PROBE_ID)),
            $this->idGenerator(),
            $eventBus,
            new MockClock(self::RECORDED_AT),
            new DefaultOoklaResultMapper(),
            new HealthEvaluator(),
            new NullLogger(),
        );

        $payload = [
            "type" => "result",
            "ping" => ["latency" => 12.5, "jitter" => 1.2],
            "download" => ["bandwidth" => 117_875_000, "bytes" => 1_200_000_000, "elapsed" => 9_000, "latency" => ["iqm" => 18.4]],
            "upload" => ["bandwidth" => 23_375_000, "bytes" => 240_000_000, "elapsed" => 8_000, "latency" => ["iqm" => 22.1]],
            "packetLoss" => 0.0,
            "isp" => "Orange Polska",
            "server" => ["id" => 12746, "name" => "Orange Polska", "location" => "Warsaw", "host" => "speedtest.orange.pl", "port" => 8080],
            "result" => ["url" => "https://www.speedtest.net/result/c/abc-123"],
        ];

        $handler(new RecordMeasurementCommand(
            new ProbeId(self::PROBE_ID),
            new ConnectionId(self::CONNECTION_ID),
            MeasurementMother::deserialize($payload),
            true,
            $payload,
        ));

        $this->assertNotNull($repository->saved);
        $this->assertSame(MeasurementStatus::Completed, $repository->saved->status());
        $this->assertSame(self::MEASUREMENT_ID, $repository->saved->id()->toString());
        $this->assertEquals(new DateTimeImmutable(self::RECORDED_AT), $repository->saved->completedAt());

        $this->assertInstanceOf(MeasurementRecorded::class, $eventBus->dispatched);
        $this->assertSame(self::MEASUREMENT_ID, $eventBus->dispatched->measurementId->toString());
        $this->assertSame(self::CONNECTION_ID, $eventBus->dispatched->connectionId->toString());
        $this->assertEquals(new DateTimeImmutable(self::RECORDED_AT), $eventBus->dispatched->occurredAt);
    }

    public function testRecordsFailedMeasurementAndStillDispatchesEvent(): void
    {
        $repository = $this->repository();
        $eventBus = $this->eventBus();

        $handler = new RecordMeasurementHandler(
            $repository,
            $this->connectionRepository(new ProbeId(self::PROBE_ID)),
            $this->idGenerator(),
            $eventBus,
            new MockClock(self::RECORDED_AT),
            new DefaultOoklaResultMapper(),
            new HealthEvaluator(),
            new NullLogger(),
        );

        $payload = ["type" => "error", "error" => "boom"];

        $handler(new RecordMeasurementCommand(
            new ProbeId(self::PROBE_ID),
            new ConnectionId(self::CONNECTION_ID),
            MeasurementMother::deserialize($payload),
            false,
            $payload,
        ));

        $this->assertNotNull($repository->saved);
        $this->assertSame(MeasurementStatus::Failed, $repository->saved->status());
        $this->assertSame($payload, $repository->saved->rawPayload());
        $this->assertInstanceOf(MeasurementRecorded::class, $eventBus->dispatched);
    }

    public function testThrowsWhenConnectionNotOwnedByProbe(): void
    {
        $repository = $this->repository();

        $handler = new RecordMeasurementHandler(
            $repository,
            $this->connectionRepository(new ProbeId("44444444-4444-4444-8444-444444444444")),
            $this->idGenerator(),
            $this->eventBus(),
            new MockClock(self::RECORDED_AT),
            new DefaultOoklaResultMapper(),
            new HealthEvaluator(),
            new NullLogger(),
        );

        $this->expectException(ConnectionNotOwnedByProbe::class);

        $handler(new RecordMeasurementCommand(
            new ProbeId(self::PROBE_ID),
            new ConnectionId(self::CONNECTION_ID),
            new OoklaResult("error"),
            false,
            ["type" => "error", "error" => "boom"],
        ));

        $this->assertNull($repository->saved);
    }

    /**
     * @return MeasurementRepository&object{saved: ?Measurement}
     */
    private function repository(): MeasurementRepository
    {
        return new class() implements MeasurementRepository {
            public ?Measurement $saved = null;

            public function save(Measurement $measurement): void
            {
                $this->saved = $measurement;
            }

            public function get(MeasurementId $id): Measurement
            {
                throw new MeasurementNotFound();
            }

            public function find(MeasurementId $id): ?Measurement
            {
                return null;
            }
        };
    }

    private function idGenerator(): IdGeneratorInterface
    {
        return new class(self::MEASUREMENT_ID) implements IdGeneratorInterface {
            public function __construct(
                private readonly string $id,
            ) {}

            public function generate(): Id
            {
                return new Id($this->id);
            }
        };
    }

    /**
     * @return MessageBusInterface&object{dispatched: ?object}
     */
    private function eventBus(): MessageBusInterface
    {
        return new class() implements MessageBusInterface {
            public ?object $dispatched = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched = $message;

                return new Envelope($message);
            }
        };
    }

    private function connectionRepository(ProbeId $owner): ConnectionRepository
    {
        $connection = new Connection(
            new ConnectionId(self::CONNECTION_ID),
            $owner,
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

            public function get(ConnectionId $id): Connection
            {
                return $this->connection;
            }

            public function find(ConnectionId $id): ?Connection
            {
                return $id->equals($this->connection->id()) ? $this->connection : null;
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
}
