<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\NotFoundException;
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function sort;

final class DoctrineConnectionRepositoryTest extends KernelTestCase
{
    private const string PROBE_A = "aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa";
    private const string PROBE_B = "bbbbbbbb-bbbb-7bbb-8bbb-bbbbbbbbbbbb";

    private ConnectionRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->repository = $container->get(ConnectionRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testSavesAndReturnsByIdentity(): void
    {
        $id = "10000000-0000-7000-8000-000000000001";
        $connection = $this->connection($id, self::PROBE_A, "Home WAN1");

        $this->repository->save($connection);
        $this->entityManager->clear();

        $loaded = $this->repository->get(new ConnectionId($id));

        $this->assertSame($id, $loaded->id()->toString());
        $this->assertSame(self::PROBE_A, $loaded->probeId()->toString());
        $this->assertSame("Home WAN1", $loaded->name());
        $this->assertSame("Orange", $loaded->isp());
        $this->assertSame(300_000_000, $loaded->expected()->expectedDownloadBits);
        $this->assertSame(50_000_000, $loaded->expected()->expectedUploadBits);
        $this->assertSame(ConnectionColor::Amber, $loaded->color());
        $this->assertInstanceOf(Labels::class, $loaded->labels());
        $this->assertSame(["site" => "home", "link" => "wan1"], $loaded->labels()->all());
        $this->assertSame("home", $loaded->labels()->get("site"));
        $this->assertInstanceOf(ServerPool::class, $loaded->serverPool());
        $this->assertSame(["frankfurt.example.net:8080", "warsaw.example.net:8080"], $loaded->serverPool()->all());
        $this->assertSame(ScheduleMode::Even, $loaded->schedule()->mode());
        $this->assertSame(24, $loaded->schedule()->testsPerDay());
        $this->assertSame(120, $loaded->schedule()->jitterSeconds());
        $this->assertTrue($loaded->isEnabled());
        $this->assertSame(0.85, $loaded->thresholds()->minDownloadRatio());
        $this->assertSame(0.6, $loaded->thresholds()->minUploadRatio());
        $this->assertSame(120.0, $loaded->thresholds()->maxPingMs());
        $this->assertNull($loaded->thresholds()->maxJitterMs());
        $this->assertSame(0.02, $loaded->thresholds()->maxPacketLossRatio());
        $this->assertSame(180, $loaded->adaptivePolicy()->adaptiveIntervalSeconds());
        $this->assertSame(2, $loaded->adaptivePolicy()->recoveryHealthyCount());
        $this->assertSame(4, $loaded->adaptivePolicy()->maxConsecutiveFailures());
    }

    public function testRoundTripsACronSchedule(): void
    {
        $id = "10000000-0000-7000-8000-000000000002";
        $connection = new Connection(
            new ConnectionId($id),
            new ProbeId(self::PROBE_A),
            "Cron WAN",
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Amber,
            Labels::fromArray(["site" => "home"]),
            ServerPool::fromArray(["frankfurt.example.net:8080"]),
            Schedule::cron("*/30 * * * *", "0 9 * * 1"),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        $this->repository->save($connection);
        $this->entityManager->clear();

        $loaded = $this->repository->get(new ConnectionId($id));

        $this->assertSame(ScheduleMode::Cron, $loaded->schedule()->mode());
        $this->assertSame(["*/30 * * * *", "0 9 * * 1"], $loaded->schedule()->cronExpressions());
    }

    public function testFindReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->repository->find(new ConnectionId("10000000-0000-7000-8000-0000000000ff")));
    }

    public function testGetThrowsNotFoundWhenAbsent(): void
    {
        $this->expectException(NotFoundException::class);

        $this->repository->get(new ConnectionId("10000000-0000-7000-8000-0000000000fe"));
    }

    public function testByProbeReturnsOnlyThatProbesConnections(): void
    {
        $this->repository->save($this->connection("10000000-0000-7000-8000-00000000000a", self::PROBE_A, "A1"));
        $this->repository->save($this->connection("10000000-0000-7000-8000-00000000000b", self::PROBE_A, "A2"));
        $this->repository->save($this->connection("10000000-0000-7000-8000-00000000000c", self::PROBE_B, "B1"));
        $this->entityManager->clear();

        $forA = $this->repository->byProbe(new ProbeId(self::PROBE_A));

        $this->assertCount(2, $forA);

        $names = array_map(static fn(Connection $c): string => $c->name(), $forA->toArray());
        sort($names);

        $this->assertSame(["A1", "A2"], $names);
    }

    public function testDeleteRemovesTheConnection(): void
    {
        $id = "10000000-0000-7000-8000-0000000000d0";
        $connection = $this->connection($id, self::PROBE_A, "ToDelete");

        $this->repository->save($connection);
        $this->entityManager->clear();

        $loaded = $this->repository->get(new ConnectionId($id));
        $this->repository->delete($loaded);
        $this->entityManager->clear();

        $this->assertNull($this->repository->find(new ConnectionId($id)));
    }

    public function testAllReturnsEveryConnectionIncludingDisabledOrderedByName(): void
    {
        $enabled = $this->connection("10000000-0000-7000-8000-0000000000aa", self::PROBE_A, "Zeta");
        $disabled = $this->connection("10000000-0000-7000-8000-0000000000ab", self::PROBE_B, "Alpha");
        $disabled->disable();

        $this->repository->save($enabled);
        $this->repository->save($disabled);
        $this->entityManager->clear();

        $all = $this->repository->all();

        $this->assertCount(2, $all);

        $names = array_map(static fn(Connection $c): string => $c->name(), $all->toArray());

        $this->assertSame(["Alpha", "Zeta"], $names);

        $enabledFlags = [];

        foreach ($all as $connection) {
            $enabledFlags[$connection->name()] = $connection->isEnabled();
        }

        $this->assertFalse($enabledFlags["Alpha"]);
        $this->assertTrue($enabledFlags["Zeta"]);
    }

    private function connection(string $id, string $probeId, string $name): Connection
    {
        return new Connection(
            new ConnectionId($id),
            new ProbeId($probeId),
            $name,
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Amber,
            Labels::fromArray(["site" => "home", "link" => "wan1"]),
            ServerPool::fromArray(["frankfurt.example.net:8080", "warsaw.example.net:8080"]),
            Schedule::even(24, 120),
            true,
            Thresholds::of(0.85, 0.6, 120.0, null, 0.02),
            AdaptivePolicy::of(180, 2, 4),
        );
    }
}
