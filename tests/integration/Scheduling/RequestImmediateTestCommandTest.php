<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling;

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
use App\Scheduling\Application\Command\RequestImmediateTest\RequestImmediateTestCommand;
use App\Scheduling\Application\Command\RequestImmediateTest\RequestImmediateTestHandler;
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Shared\Domain\NotFoundException;
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestImmediateTestCommandTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";
    private const string CONNECTION = "10000000-0000-7000-8000-0000000000c1";
    private const string CONNECTION_2 = "10000000-0000-7000-8000-0000000000c2";
    private const string CONNECTION_DISABLED = "10000000-0000-7000-8000-0000000000c3";

    private MessageBusInterface $commandBus;
    private ConnectionRepository $connections;
    private DueNowMarkerRepository $markers;
    private RequestImmediateTestHandler $handler;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->connections = $container->get(ConnectionRepository::class);
        $this->markers = $container->get(DueNowMarkerRepository::class);
        $this->handler = $container->get(RequestImmediateTestHandler::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM due_now_markers");
        $this->dbal->executeStatement("DELETE FROM connections");
    }

    public function testDispatchMarksAnExistingConnectionDueNow(): void
    {
        $this->connections->save($this->connection());

        $this->commandBus->dispatch(new RequestImmediateTestCommand("connection", self::CONNECTION, null));

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE));

        self::assertCount(1, $pulled);
        self::assertSame(self::CONNECTION, $pulled->toArray()[0]->connectionId->toString());
        self::assertNull($pulled->toArray()[0]->forcedServerId);
    }

    public function testConnectionScopePinsTheForcedServerOnTheMarker(): void
    {
        $this->connections->save($this->connection());

        ($this->handler)(new RequestImmediateTestCommand("connection", self::CONNECTION, "12345"));

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE));

        self::assertCount(1, $pulled);
        self::assertSame(self::CONNECTION, $pulled->toArray()[0]->connectionId->toString());
        self::assertSame("12345", $pulled->toArray()[0]->forcedServerId);
    }

    public function testEmptyStringPinNormalisesToNull(): void
    {
        $this->connections->save($this->connection());

        ($this->handler)(new RequestImmediateTestCommand("connection", self::CONNECTION, ""));

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE));

        self::assertCount(1, $pulled);
        self::assertNull($pulled->toArray()[0]->forcedServerId);
    }

    public function testAllScopeMarksEveryEnabledConnectionWithNullPinAndSkipsDisabled(): void
    {
        $this->connections->save($this->connection(self::CONNECTION, true));
        $this->connections->save($this->connection(self::CONNECTION_2, true));
        $this->connections->save($this->connection(self::CONNECTION_DISABLED, false));

        ($this->handler)(new RequestImmediateTestCommand("all", null, null));

        $pulled = $this->markers->pullForProbe(new ProbeId(self::PROBE));

        $marked = [];

        foreach ($pulled->toArray() as $row) {
            $marked[$row->connectionId->toString()] = $row->forcedServerId;
        }

        self::assertCount(2, $pulled);
        self::assertArrayHasKey(self::CONNECTION, $marked);
        self::assertArrayHasKey(self::CONNECTION_2, $marked);
        self::assertArrayNotHasKey(self::CONNECTION_DISABLED, $marked);
        self::assertNull($marked[self::CONNECTION]);
        self::assertNull($marked[self::CONNECTION_2]);
    }

    public function testHandlingUnknownConnectionThrowsAndMarksNothing(): void
    {
        try {
            ($this->handler)(new RequestImmediateTestCommand("connection", "10000000-0000-7000-8000-0000000000ff", null));
            self::fail("Expected NotFoundException for an unknown connection.");
        } catch (NotFoundException) {
            self::assertSame([], $this->dbal->fetchFirstColumn("SELECT connection_id FROM due_now_markers"));
        }
    }

    private function connection(string $id = self::CONNECTION, bool $enabled = true): Connection
    {
        return new Connection(
            new ConnectionId($id),
            new ProbeId(self::PROBE),
            "wan",
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Amber,
            Labels::fromArray([]),
            ServerPool::fromArray(["frankfurt.example.net:8080"]),
            Schedule::even(24, 0),
            $enabled,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
