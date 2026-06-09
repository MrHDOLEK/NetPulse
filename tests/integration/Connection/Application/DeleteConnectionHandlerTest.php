<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection\Application;

use App\Connection\Application\Command\DeleteConnection\DeleteConnectionCommand;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Exception\ConnectionNotFound;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeleteConnectionHandlerTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";
    private const string OTHER_PROBE = "dddddddd-dddd-7ddd-8ddd-dddddddddddd";
    private const string CONNECTION = "10000000-0000-7000-8000-0000000000d1";

    private MessageBusInterface $commandBus;
    private ConnectionRepository $connections;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->connections = $container->get(ConnectionRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM connections");
    }

    public function testDeletesAnOwnedConnection(): void
    {
        $this->connections->save($this->connection());

        $this->commandBus->dispatch(new DeleteConnectionCommand(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
        ));

        $this->entityManager->clear();
        $this->assertNull($this->connections->find(new ConnectionId(self::CONNECTION)));
    }

    public function testRejectsDeleteUnderAWrongProbe(): void
    {
        $this->connections->save($this->connection());

        try {
            $this->commandBus->dispatch(new DeleteConnectionCommand(
                new ConnectionId(self::CONNECTION),
                new ProbeId(self::OTHER_PROBE),
            ));
            self::fail("Expected ConnectionNotFound for an ownership mismatch.");
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(ConnectionNotFound::class, $exception->getPrevious());
        }

        $this->entityManager->clear();
        $this->assertNotNull($this->connections->find(new ConnectionId(self::CONNECTION)));
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
