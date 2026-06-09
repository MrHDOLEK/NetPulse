<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\GetDueWork;

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
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Application\GetDueWork\GetDueWorkHandler;
use App\Scheduling\Application\GetDueWork\GetDueWorkQuery;
use App\Scheduling\Application\LastMeasurementRepository;
use App\Scheduling\Application\LastMeasurementRow;
use App\Scheduling\Application\LastMeasurementRowCollection;
use App\Scheduling\Domain\DueWorkCalculator;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Infrastructure\Cron\DragonmantankCronEvaluator;
use App\Shared\Domain\NotFoundException;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

final class GetDueWorkHandlerTest extends TestCase
{
    private const string PROBE_ID = "11111111-1111-4111-8111-111111111111";
    private const string DUE_CONNECTION = "22222222-2222-4222-8222-222222222222";
    private const string RECENT_CONNECTION = "33333333-3333-4333-8333-333333333333";
    private const string DISABLED_CONNECTION = "44444444-4444-4444-8444-444444444444";
    private const string NEVER_MEASURED_CONNECTION = "55555555-5555-4555-8555-555555555555";
    private const string NOW = "2026-06-06 12:00:00";

    public function testReturnsOnlyDueEnabledConnections(): void
    {
        $probeId = new ProbeId(self::PROBE_ID);

        $due = $this->connection(self::DUE_CONNECTION, $probeId, true);
        $recent = $this->connection(self::RECENT_CONNECTION, $probeId, true);
        $disabledButDue = $this->connection(self::DISABLED_CONNECTION, $probeId, false);
        $neverMeasured = $this->connection(self::NEVER_MEASURED_CONNECTION, $probeId, true);

        $lastMeasurements = new InMemoryLastMeasurementRepository(
            new LastMeasurementRow(new ConnectionId(self::DUE_CONNECTION), $this->ago(7200), "old", HealthHistory::empty()),
            new LastMeasurementRow(new ConnectionId(self::RECENT_CONNECTION), $this->ago(60), "old", HealthHistory::empty()),
            new LastMeasurementRow(new ConnectionId(self::DISABLED_CONNECTION), $this->ago(7200), "old", HealthHistory::empty()),
        );

        $handler = new GetDueWorkHandler(
            new InMemoryConnectionRepository($due, $recent, $disabledButDue, $neverMeasured),
            $lastMeasurements,
            new DueWorkCalculator(new DragonmantankCronEvaluator()),
            new InMemoryDueNowMarkerRepository(),
            new InMemoryRunStateRepository(),
            new MockClock(self::NOW),
            new NullLogger(),
            42,
        );

        $dueWork = $handler(new GetDueWorkQuery($probeId));

        $tasks = $dueWork->tasks->toArray();
        $connectionIds = array_map(static fn($task): string => $task->connectionId->toString(), $tasks);

        sort($connectionIds);

        self::assertSame([self::DUE_CONNECTION, self::NEVER_MEASURED_CONNECTION], $connectionIds);
        self::assertSame(42, $dueWork->pollAfterSeconds);

        $byId = [];

        foreach ($tasks as $task) {
            $byId[$task->connectionId->toString()] = $task->serverId;
        }

        self::assertSame("x", $byId[self::DUE_CONNECTION]);
        self::assertSame("x", $byId[self::NEVER_MEASURED_CONNECTION]);
    }

    private function connection(string $id, ProbeId $probeId, bool $enabled): Connection
    {
        return new Connection(
            new ConnectionId($id),
            $probeId,
            "wan",
            "isp",
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::fromArray([]),
            ServerPool::fromList("x"),
            Schedule::even(24, 0),
            $enabled,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }

    private function ago(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable(self::NOW))->modify("-{$seconds} seconds");
    }
}

final class InMemoryConnectionRepository implements ConnectionRepository
{
    /** @var list<Connection> */
    private array $connections;

    public function __construct(Connection ...$connections)
    {
        $this->connections = array_values($connections);
    }

    public function save(Connection $connection): void
    {
    }

    public function delete(Connection $connection): void
    {
    }

    public function get(ConnectionId $connectionId): Connection
    {
        throw new NotFoundException("not found");
    }

    public function find(ConnectionId $connectionId): ?Connection
    {
        return null;
    }

    public function byProbe(ProbeId $probeId): ConnectionCollection
    {
        return ConnectionCollection::fromList($this->connections);
    }

    public function allEnabled(): ConnectionCollection
    {
        return ConnectionCollection::fromList(
            array_values(array_filter($this->connections, static fn(Connection $c): bool => $c->isEnabled())),
        );
    }

    public function all(): ConnectionCollection
    {
        return ConnectionCollection::fromList($this->connections);
    }
}

final class InMemoryLastMeasurementRepository implements LastMeasurementRepository
{
    /** @var list<LastMeasurementRow> */
    private array $rows;

    public function __construct(LastMeasurementRow ...$rows)
    {
        $this->rows = array_values($rows);
    }

    public function forProbe(ProbeId $probeId): LastMeasurementRowCollection
    {
        return LastMeasurementRowCollection::fromList($this->rows);
    }
}
