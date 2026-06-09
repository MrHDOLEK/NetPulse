<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe\Application;

use App\Probe\Application\Command\SetProbeEnabled\SetProbeEnabledCommand;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class SetProbeEnabledHandlerTest extends KernelTestCase
{
    private const string PROBE = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";

    private MessageBusInterface $commandBus;
    private ProbeRepository $probes;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->commandBus = $container->get("command.bus");
        $this->probes = $container->get(ProbeRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM connections");
        $this->dbal->executeStatement("DELETE FROM probes");
    }

    public function testDisablesThenReEnablesAProbe(): void
    {
        $this->probes->save($this->probe(enabled: true));

        $this->commandBus->dispatch(new SetProbeEnabledCommand(new ProbeId(self::PROBE), false));

        $this->entityManager->clear();
        $this->assertFalse($this->probes->get(new ProbeId(self::PROBE))->isEnabled());

        $this->commandBus->dispatch(new SetProbeEnabledCommand(new ProbeId(self::PROBE), true));

        $this->entityManager->clear();
        $this->assertTrue($this->probes->get(new ProbeId(self::PROBE))->isEnabled());
    }

    private function probe(bool $enabled): Probe
    {
        return new Probe(
            new ProbeId(self::PROBE),
            "edge-01",
            Labels::empty(),
            "hash",
            $enabled,
            new DateTimeImmutable("2026-01-01T00:00:00+00:00"),
        );
    }
}
