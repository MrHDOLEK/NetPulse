<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Doctrine;

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
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Scheduling\Domain\ValueObject\MarkedConnection;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function sort;

final class DoctrineDueNowMarkerRepositoryTest extends KernelTestCase
{
    private const string PROBE_A = "aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa";
    private const string PROBE_B = "bbbbbbbb-bbbb-7bbb-8bbb-bbbbbbbbbbbb";
    private const string CONN_A1 = "10000000-0000-7000-8000-0000000000a1";
    private const string CONN_A2 = "10000000-0000-7000-8000-0000000000a2";
    private const string CONN_B1 = "10000000-0000-7000-8000-0000000000b1";

    private DueNowMarkerRepository $markers;
    private ConnectionRepository $connections;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->markers = $container->get(DueNowMarkerRepository::class);
        $this->connections = $container->get(ConnectionRepository::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM due_now_markers");
    }

    public function testPullForProbeReturnsAndDeletesOnlyThatProbesMarkers(): void
    {
        $this->connections->save($this->connection(self::CONN_A1, self::PROBE_A));
        $this->connections->save($this->connection(self::CONN_A2, self::PROBE_A));
        $this->connections->save($this->connection(self::CONN_B1, self::PROBE_B));

        $now = new DateTimeImmutable("2026-06-06 12:00:00");
        $this->markers->mark(new ConnectionId(self::CONN_A1), $now, null);
        $this->markers->mark(new ConnectionId(self::CONN_A2), $now, null);
        $this->markers->mark(new ConnectionId(self::CONN_B1), $now, null);

        $pulledA = $this->markers->pullForProbe(new ProbeId(self::PROBE_A));
        $idsA = array_map(static fn(MarkedConnection $m): string => $m->connectionId->toString(), $pulledA->toArray());
        sort($idsA);

        self::assertSame([self::CONN_A1, self::CONN_A2], $idsA);

        $remaining = $this->dbal->fetchFirstColumn("SELECT connection_id FROM due_now_markers ORDER BY connection_id");
        self::assertSame([self::CONN_B1], $remaining);

        self::assertCount(0, $this->markers->pullForProbe(new ProbeId(self::PROBE_A)));

        $pulledB = $this->markers->pullForProbe(new ProbeId(self::PROBE_B));
        $idsB = array_map(static fn(MarkedConnection $m): string => $m->connectionId->toString(), $pulledB->toArray());

        self::assertSame([self::CONN_B1], $idsB);
        self::assertSame([], $this->dbal->fetchFirstColumn("SELECT connection_id FROM due_now_markers"));
    }

    public function testPullForProbeRoundTripsThePinnedServerAndDeletesTheMarker(): void
    {
        $this->connections->save($this->connection(self::CONN_A1, self::PROBE_A));

        $now = new DateTimeImmutable("2026-06-06 12:00:00");
        $this->markers->mark(new ConnectionId(self::CONN_A1), $now, "12345");

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE_A));

        self::assertCount(1, $pulled);
        $marked = $pulled->toArray()[0];
        self::assertSame(self::CONN_A1, $marked->connectionId->toString());
        self::assertSame("12345", $marked->forcedServerId);

        self::assertSame([], $this->dbal->fetchFirstColumn("SELECT connection_id FROM due_now_markers"));
    }

    public function testPullForProbeRoundTripsANullPin(): void
    {
        $this->connections->save($this->connection(self::CONN_A1, self::PROBE_A));

        $this->markers->mark(new ConnectionId(self::CONN_A1), new DateTimeImmutable("2026-06-06 12:00:00"), null);

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE_A));

        self::assertCount(1, $pulled);
        self::assertNull($pulled->toArray()[0]->forcedServerId);
    }

    public function testMarkIsIdempotentUpsert(): void
    {
        $this->connections->save($this->connection(self::CONN_A1, self::PROBE_A));

        $this->markers->mark(new ConnectionId(self::CONN_A1), new DateTimeImmutable("2026-06-06 12:00:00"), null);

        $this->markers->mark(new ConnectionId(self::CONN_A1), new DateTimeImmutable("2026-06-06 12:05:00"), "99");

        $rows = $this->dbal->fetchAllAssociative("SELECT connection_id, requested_at, forced_server_id FROM due_now_markers");

        self::assertCount(1, $rows);
        self::assertSame(self::CONN_A1, $rows[0]["connection_id"]);
        self::assertStringContainsString("12:05:00", (string)$rows[0]["requested_at"]);

        self::assertSame("99", $rows[0]["forced_server_id"]);
    }

    private function connection(string $id, string $probeId): Connection
    {
        return new Connection(
            new ConnectionId($id),
            new ProbeId($probeId),
            "wan",
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Amber,
            Labels::fromArray([]),
            ServerPool::fromArray(["frankfurt.example.net:8080"]),
            Schedule::even(24, 0),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
