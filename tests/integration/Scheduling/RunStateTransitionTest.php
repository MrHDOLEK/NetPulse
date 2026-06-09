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
use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Application\Command\RequestImmediateTest\RequestImmediateTestCommand;
use App\Scheduling\Application\GetDueWork\GetDueWorkHandler;
use App\Scheduling\Application\GetDueWork\GetDueWorkQuery;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class RunStateTransitionTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";
    private const string CONNECTION = "10000000-0000-7000-8000-0000000000d1";

    private MessageBusInterface $commandBus;
    private MessageBusInterface $eventBus;
    private GetDueWorkHandler $getDueWork;
    private ConnectionRepository $connections;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->eventBus = $container->get("event.bus");
        $this->getDueWork = $container->get(GetDueWorkHandler::class);
        $this->connections = $container->get(ConnectionRepository::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM run_states");
        $this->dbal->executeStatement("DELETE FROM due_now_markers");
        $this->dbal->executeStatement("DELETE FROM connections");
    }

    public function testRunStateMovesQueuedThenRunningThenDoneAcrossTheRealFlow(): void
    {
        $this->connections->save($this->connection());

        $this->commandBus->dispatch(new RequestImmediateTestCommand("connection", self::CONNECTION, null));
        self::assertSame("queued", $this->phase());

        ($this->getDueWork)(new GetDueWorkQuery(new ProbeId(self::PROBE)));
        self::assertSame("running", $this->phase());

        self::assertSame(0, $this->markerCount());

        $this->eventBus->dispatch(new MeasurementRecorded(
            new MeasurementId("eeeeeeee-0000-7000-8000-000000000001"),
            new ProbeId(self::PROBE),
            new ConnectionId(self::CONNECTION),
            new DateTimeImmutable("now", new DateTimeZone("UTC")),
        ));
        self::assertSame("done", $this->phase());
    }

    public function testRecordingAMeasurementWithNoRunInFlightCreatesNoRunStateRow(): void
    {
        $this->connections->save($this->connection());

        $this->eventBus->dispatch(new MeasurementRecorded(
            new MeasurementId("eeeeeeee-0000-7000-8000-000000000002"),
            new ProbeId(self::PROBE),
            new ConnectionId(self::CONNECTION),
            new DateTimeImmutable("now", new DateTimeZone("UTC")),
        ));

        self::assertSame(0, $this->runStateCount());
    }

    private function phase(): ?string
    {
        $value = $this->dbal->fetchOne(
            "SELECT phase FROM run_states WHERE connection_id = :id",
            ["id" => self::CONNECTION],
        );

        return $value === false ? null : (string)$value;
    }

    private function runStateCount(): int
    {
        return (int)$this->dbal->fetchOne("SELECT COUNT(*) FROM run_states");
    }

    private function markerCount(): int
    {
        return (int)$this->dbal->fetchOne(
            "SELECT COUNT(*) FROM due_now_markers WHERE connection_id = :id",
            ["id" => self::CONNECTION],
        );
    }

    private function connection(): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
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
