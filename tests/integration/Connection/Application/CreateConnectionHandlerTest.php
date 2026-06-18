<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection\Application;

use App\Connection\Application\Command\CreateConnection\CreateConnectionCommand;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\NotFoundException;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateConnectionHandlerTest extends KernelTestCase
{
    private const string PROBE = 'cccccccc-cccc-7ccc-8ccc-cccccccccccc';

    private MessageBusInterface $commandBus;
    private ConnectionRepository $connections;
    private ProbeRepository $probes;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get('command.bus');
        $this->connections = $container->get(ConnectionRepository::class);
        $this->probes = $container->get(ProbeRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement('DELETE FROM connections');
        $this->dbal->executeStatement('DELETE FROM probes');
    }

    public function testCreatesAConnectionForAnExistingProbe(): void
    {
        $this->persistProbe();

        $this->commandBus->dispatch($this->command());

        $this->entityManager->clear();

        $stored = $this->connections->byProbe(new ProbeId(self::PROBE));

        $this->assertCount(1, $stored);

        $connection = $stored->toArray()[0];
        $this->assertSame('Home WAN1', $connection->name());
        $this->assertSame('Orange', $connection->isp());
        $this->assertSame(ConnectionColor::Violet, $connection->color());
        $this->assertTrue($connection->isEnabled());
    }

    public function testThrowsWhenProbeIsMissing(): void
    {
        try {
            $this->commandBus->dispatch($this->command());
            self::fail('Expected the missing probe to abort creation.');
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious();
            self::assertInstanceOf(ProbeNotFound::class, $cause);
            self::assertInstanceOf(NotFoundException::class, $cause);
        }

        $this->entityManager->clear();
        $this->assertCount(0, $this->connections->byProbe(new ProbeId(self::PROBE)));
    }

    private function command(): CreateConnectionCommand
    {
        return new CreateConnectionCommand(
            new ProbeId(self::PROBE),
            'Home WAN1',
            'Orange',
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Violet,
            Labels::fromArray(['site' => 'home']),
            ServerPool::fromArray(['frankfurt.example.net:8080']),
            Schedule::even(24, 120),
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }

    private function persistProbe(): void
    {
        $this->probes->save(
            new Probe(
                new ProbeId(self::PROBE),
                'edge-01',
                Labels::empty(),
                'hash',
                true,
                new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            ),
        );
    }
}
