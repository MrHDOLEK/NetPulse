<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe\Application;

use App\Probe\Application\Command\CreateProbe\CreateProbeCommand;
use App\Probe\Application\Command\CreateProbe\ProbeCreated;
use App\Probe\Application\Command\RotateProbeToken\ProbeTokenRotated;
use App\Probe\Application\Command\RotateProbeToken\RotateProbeTokenCommand;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

use function str_contains;

final class RotateProbeTokenHandlerTest extends KernelTestCase
{
    private MessageBusInterface $commandBus;
    private ProbeRepository $probes;
    private ProbeTokenHasher $hasher;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->probes = $container->get(ProbeRepository::class);
        $this->hasher = $container->get(ProbeTokenHasher::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM connections");
        $this->dbal->executeStatement("DELETE FROM probes");
    }

    public function testRotatingSurfacesTheNewPlaintextOnceAndInvalidatesTheOldToken(): void
    {
        $created = $this->commandBus->dispatch(new CreateProbeCommand("edge-01", Labels::empty()))
            ->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(ProbeCreated::class, $created);

        $probeId = $created->probeId;
        $oldToken = $created->plaintextToken;

        $this->entityManager->clear();

        $rotated = $this->commandBus->dispatch(new RotateProbeTokenCommand($probeId))
            ->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(ProbeTokenRotated::class, $rotated);

        $newToken = $rotated->plaintextToken;
        self::assertNotSame($oldToken, $newToken);

        $this->entityManager->clear();
        $probe = $this->probes->get($probeId);

        self::assertTrue($probe->verifyToken($newToken, $this->hasher));
        self::assertFalse($probe->verifyToken($oldToken, $this->hasher));

        self::assertFalse(str_contains($probe->tokenHash(), $newToken));
    }
}
