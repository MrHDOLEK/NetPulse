<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe\Application;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Application\Command\DeleteProbe\DeleteProbeCommand;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\ProbeHasConnections;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeleteProbeHandlerTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";
    private const string CONNECTION = "10000000-0000-7000-8000-0000000000a9";

    private MessageBusInterface $commandBus;
    private ProbeRepository $probes;
    private ConnectionRepository $connections;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->probes = $container->get(ProbeRepository::class);
        $this->connections = $container->get(ConnectionRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM connections");
        $this->dbal->executeStatement("DELETE FROM probes");
    }

    public function testDeletesAProbeWithNoConnections(): void
    {
        $this->probes->save($this->probe());

        $this->commandBus->dispatch(new DeleteProbeCommand(new ProbeId(self::PROBE)));

        $this->entityManager->clear();
        $this->assertNull($this->probes->find(new ProbeId(self::PROBE)));
    }

    public function testRejectsDeletionWhenTheProbeStillOwnsConnections(): void
    {
        $this->probes->save($this->probe());
        $this->connections->save($this->connection());

        try {
            $this->commandBus->dispatch(new DeleteProbeCommand(new ProbeId(self::PROBE)));
            self::fail("Expected ProbeHasConnections for a probe with connections.");
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(ProbeHasConnections::class, $exception->getPrevious());
        }

        $this->entityManager->clear();

        $this->assertNotNull($this->probes->find(new ProbeId(self::PROBE)));
    }

    public function testThrowsWhenTheProbeIsMissing(): void
    {
        try {
            $this->commandBus->dispatch(new DeleteProbeCommand(new ProbeId(self::PROBE)));
            self::fail("Expected ProbeNotFound for a missing probe.");
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(ProbeNotFound::class, $exception->getPrevious());
        }
    }

    private function probe(): Probe
    {
        return new Probe(
            new ProbeId(self::PROBE),
            "edge-01",
            Labels::empty(),
            "hash",
            true,
            new DateTimeImmutable("2026-01-01T00:00:00+00:00"),
        );
    }

    private function connection(): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
            "Home WAN1",
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::empty(),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
