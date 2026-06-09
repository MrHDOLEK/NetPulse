<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection\Application;

use App\Connection\Application\Command\SetConnectionEnabled\SetConnectionEnabledCommand;
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
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class SetConnectionEnabledHandlerTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";
    private const string CONNECTION = "10000000-0000-7000-8000-0000000000f1";

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

    public function testDisablesThenReEnablesAConnection(): void
    {
        $this->connections->save($this->connection(enabled: true));

        $this->commandBus->dispatch(new SetConnectionEnabledCommand(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
            false,
        ));

        $this->entityManager->clear();
        $this->assertFalse($this->connections->get(new ConnectionId(self::CONNECTION))->isEnabled());

        $this->commandBus->dispatch(new SetConnectionEnabledCommand(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
            true,
        ));

        $this->entityManager->clear();
        $this->assertTrue($this->connections->get(new ConnectionId(self::CONNECTION))->isEnabled());
    }

    private function connection(bool $enabled): Connection
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
            $enabled,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
