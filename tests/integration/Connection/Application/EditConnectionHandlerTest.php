<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection\Application;

use App\Connection\Application\Command\EditConnection\EditConnectionCommand;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Enum\ScheduleMode;
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

final class EditConnectionHandlerTest extends KernelTestCase
{
    private const string PROBE = 'cccccccc-cccc-7ccc-8ccc-cccccccccccc';
    private const string OTHER_PROBE = 'dddddddd-dddd-7ddd-8ddd-dddddddddddd';
    private const string CONNECTION = '10000000-0000-7000-8000-0000000000e1';

    private MessageBusInterface $commandBus;
    private ConnectionRepository $connections;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get('command.bus');
        $this->connections = $container->get(ConnectionRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement('DELETE FROM connections');
    }

    public function testReplacesEveryEditableFieldAndLeavesIdentityAndEnabledUntouched(): void
    {
        $this->connections->save($this->connection(enabled: false));

        $this->commandBus->dispatch(
            new EditConnectionCommand(
                new ConnectionId(self::CONNECTION),
                new ProbeId(self::PROBE),
                'Renamed',
                'Vodafone',
                new ExpectedSpeed(900_000_000, 90_000_000),
                ConnectionColor::Amber,
                Labels::fromArray(['env' => 'prod']),
                ServerPool::fromArray(['paris.example.net:8080']),
                Schedule::cron('*/15 * * * *'),
                Thresholds::of(0.9, 0.5, null, null, null),
                AdaptivePolicy::of(60, 1, 2),
            ),
        );

        $this->entityManager->clear();
        $loaded = $this->connections->get(new ConnectionId(self::CONNECTION));

        $this->assertSame('Renamed', $loaded->name());
        $this->assertSame('Vodafone', $loaded->isp());
        $this->assertSame(900_000_000, $loaded->expected()->expectedDownloadBits);
        $this->assertSame(ConnectionColor::Amber, $loaded->color());
        $this->assertSame(['env' => 'prod'], $loaded->labels()->all());
        $this->assertSame(['paris.example.net:8080'], $loaded->serverPool()->all());
        $this->assertSame(ScheduleMode::Cron, $loaded->schedule()->mode());
        $this->assertSame(['*/15 * * * *'], $loaded->schedule()->cronExpressions());
        $this->assertSame(0.9, $loaded->thresholds()->minDownloadRatio());
        $this->assertSame(60, $loaded->adaptivePolicy()->adaptiveIntervalSeconds());

        $this->assertSame(self::CONNECTION, $loaded->id()->toString());
        $this->assertSame(self::PROBE, $loaded->probeId()->toString());
        $this->assertFalse($loaded->isEnabled());
    }

    public function testRejectsEditUnderAWrongProbeAsNotFound(): void
    {
        $this->connections->save($this->connection());

        try {
            $this->commandBus->dispatch(
                new EditConnectionCommand(
                    new ConnectionId(self::CONNECTION),
                    new ProbeId(self::OTHER_PROBE),
                    'Hijacked',
                    'Orange',
                    new ExpectedSpeed(0, 0),
                    ConnectionColor::Primary,
                    Labels::empty(),
                    ServerPool::empty(),
                    Schedule::even(24, 120),
                    Thresholds::default(),
                    AdaptivePolicy::default(),
                ),
            );
            self::fail('Expected ConnectionNotFound for an ownership mismatch.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(ConnectionNotFound::class, $exception->getPrevious());
        }

        $this->entityManager->clear();

        $this->assertSame('Original', $this->connections->get(new ConnectionId(self::CONNECTION))->name());
    }

    private function connection(bool $enabled = true): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION),
            new ProbeId(self::PROBE),
            'Original',
            'Orange',
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
