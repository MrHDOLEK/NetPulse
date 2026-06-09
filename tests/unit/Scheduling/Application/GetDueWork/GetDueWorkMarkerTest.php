<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\GetDueWork;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Application\DueWork;
use App\Scheduling\Application\GetDueWork\GetDueWorkHandler;
use App\Scheduling\Application\GetDueWork\GetDueWorkQuery;
use App\Scheduling\Application\LastMeasurementRow;
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Scheduling\Domain\DueWorkCalculator;
use App\Scheduling\Domain\MarkedConnectionCollection;
use App\Scheduling\Domain\RunStateRepository;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\MarkedConnection;
use App\Scheduling\Domain\ValueObject\RunPhase;
use App\Scheduling\Infrastructure\Cron\DragonmantankCronEvaluator;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

use function array_map;

final class GetDueWorkMarkerTest extends TestCase
{
    private const string PROBE_ID = "11111111-1111-4111-8111-111111111111";
    private const string OTHER_PROBE_ID = "99999999-9999-4999-8999-999999999999";
    private const string RECENT_CONNECTION = "33333333-3333-4333-8333-333333333333";
    private const string OTHER_PROBE_CONNECTION = "66666666-6666-4666-8666-666666666666";
    private const string NOW = "2026-06-06 12:00:00";

    public function testMarkedConnectionIsForcedDueThenConsumedOnSecondPoll(): void
    {
        $probeId = new ProbeId(self::PROBE_ID);

        $recent = $this->connection(self::RECENT_CONNECTION, $probeId);

        $lastMeasurements = new InMemoryLastMeasurementRepository(
            new LastMeasurementRow(new ConnectionId(self::RECENT_CONNECTION), $this->ago(60), "old", HealthHistory::empty()),
        );

        $markers = new InMemoryDueNowMarkerRepository();
        $markers->mark(new ConnectionId(self::RECENT_CONNECTION), new DateTimeImmutable(self::NOW), null);

        $runStates = new InMemoryRunStateRepository();
        $runStates->upsert(new ConnectionId(self::RECENT_CONNECTION), RunPhase::Queued, new DateTimeImmutable(self::NOW));

        $handler = new GetDueWorkHandler(
            new InMemoryConnectionRepository($recent),
            $lastMeasurements,
            new DueWorkCalculator(new DragonmantankCronEvaluator()),
            $markers,
            $runStates,
            new MockClock(self::NOW),
            new NullLogger(),
            42,
        );

        $first = $handler(new GetDueWorkQuery($probeId));
        $firstIds = $this->connectionIds($first);

        self::assertContains(self::RECENT_CONNECTION, $firstIds);

        $serverById = [];

        foreach ($first->tasks->toArray() as $task) {
            $serverById[$task->connectionId->toString()] = $task->serverId;
        }

        self::assertSame("x", $serverById[self::RECENT_CONNECTION]);

        self::assertSame(RunPhase::Running, $runStates->phases[self::RECENT_CONNECTION]);

        $second = $handler(new GetDueWorkQuery($probeId));

        self::assertNotContains(self::RECENT_CONNECTION, $this->connectionIds($second));
    }

    public function testPinnedMarkerForcesTheExactServerSkippingRoundRobin(): void
    {
        $probeId = new ProbeId(self::PROBE_ID);

        $recent = $this->connection(self::RECENT_CONNECTION, $probeId);

        $lastMeasurements = new InMemoryLastMeasurementRepository(
            new LastMeasurementRow(new ConnectionId(self::RECENT_CONNECTION), $this->ago(60), "old", HealthHistory::empty()),
        );

        $markers = new InMemoryDueNowMarkerRepository();
        $markers->mark(new ConnectionId(self::RECENT_CONNECTION), new DateTimeImmutable(self::NOW), "99");

        $handler = new GetDueWorkHandler(
            new InMemoryConnectionRepository($recent),
            $lastMeasurements,
            new DueWorkCalculator(new DragonmantankCronEvaluator()),
            $markers,
            new InMemoryRunStateRepository(),
            new MockClock(self::NOW),
            new NullLogger(),
            42,
        );

        $dueWork = $handler(new GetDueWorkQuery($probeId));

        $serverById = [];

        foreach ($dueWork->tasks->toArray() as $task) {
            $serverById[$task->connectionId->toString()] = $task->serverId;
        }

        self::assertSame("99", $serverById[self::RECENT_CONNECTION]);
    }

    public function testMarkerForAnotherProbeDoesNotLeakIntoThisProbesDueList(): void
    {
        $probeId = new ProbeId(self::PROBE_ID);

        $recent = $this->connection(self::RECENT_CONNECTION, $probeId);

        $lastMeasurements = new InMemoryLastMeasurementRepository(
            new LastMeasurementRow(new ConnectionId(self::RECENT_CONNECTION), $this->ago(60), "old", HealthHistory::empty()),
        );

        $markers = new InMemoryDueNowMarkerRepository();
        $markers->setProbe(self::OTHER_PROBE_CONNECTION, self::OTHER_PROBE_ID);
        $markers->mark(new ConnectionId(self::OTHER_PROBE_CONNECTION), new DateTimeImmutable(self::NOW), null);

        $handler = new GetDueWorkHandler(
            new InMemoryConnectionRepository($recent),
            $lastMeasurements,
            new DueWorkCalculator(new DragonmantankCronEvaluator()),
            $markers,
            new InMemoryRunStateRepository(),
            new MockClock(self::NOW),
            new NullLogger(),
            42,
        );

        $dueWork = $handler(new GetDueWorkQuery($probeId));

        self::assertSame([], $this->connectionIds($dueWork));
    }

    /**
     * @return list<string>
     */
    private function connectionIds(DueWork $dueWork): array
    {
        return array_map(
            static fn($task): string => $task->connectionId->toString(),
            $dueWork->tasks->toArray(),
        );
    }

    private function connection(string $id, ProbeId $probeId): Connection
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
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }

    private function ago(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable(self::NOW))->modify("-{$seconds} seconds");
    }
}

final class InMemoryDueNowMarkerRepository implements DueNowMarkerRepository
{
    private const string DEFAULT_PROBE = "11111111-1111-4111-8111-111111111111";

    /** @var array<string, ?string> connectionId => forcedServerId (null = no pin) */
    private array $marked = [];

    /** @var array<string, string> connectionId => probeId */
    private array $probeOf = [];

    public function setProbe(string $connectionId, string $probeId): void
    {
        $this->probeOf[$connectionId] = $probeId;
    }

    public function mark(ConnectionId $connectionId, DateTimeImmutable $requestedAt, ?string $forcedServerId): void
    {
        $this->marked[$connectionId->toString()] = $forcedServerId;
    }

    public function pullForProbe(ProbeId $probeId): MarkedConnectionCollection
    {
        $marked = [];

        foreach ($this->marked as $connectionId => $forcedServerId) {
            $owner = $this->probeOf[$connectionId] ?? self::DEFAULT_PROBE;

            if ($owner === $probeId->toString()) {
                $marked[] = new MarkedConnection(new ConnectionId($connectionId), $forcedServerId);
                unset($this->marked[$connectionId]);
            }
        }

        return MarkedConnectionCollection::fromList($marked);
    }
}

final class InMemoryRunStateRepository implements RunStateRepository
{
    /** @var array<string, RunPhase> connectionId => phase */
    public array $phases = [];

    public function upsert(ConnectionId $connectionId, RunPhase $phase, DateTimeImmutable $at): void
    {
        $this->phases[$connectionId->toString()] = $phase;
    }

    public function markDoneIfPending(ConnectionId $connectionId, DateTimeImmutable $at): void
    {
        if (isset($this->phases[$connectionId->toString()])) {
            $this->phases[$connectionId->toString()] = RunPhase::Done;
        }
    }
}
