<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dev;

use App\Connection\Domain\ConnectionRepository;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Metrics\Application\MetricsRepository;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends KernelTestCase
{
    public function testItSeedsProbeConnectionsAndMeasurements(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $application = new Application(self::$kernel);
        $command = $application->find('app:dev:seed');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            '--measurements' => '12',
        ]);

        $this->assertSame(0, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Probe token:', $output);
        $this->assertMatchesRegularExpression("/Probe token: \S+/", $output);

        $probeIdMatched = [];
        $this->assertSame(1, preg_match("/Probe id: (\S+)/", $output, $probeIdMatched));

        /** @var ProbeRepository $probeRepository */
        $probeRepository = $container->get(ProbeRepository::class);
        /** @var ConnectionRepository $connectionRepository */
        $connectionRepository = $container->get(ConnectionRepository::class);
        /** @var MetricsRepository $metricsRepository */
        $metricsRepository = $container->get(MetricsRepository::class);

        $probe = $probeRepository->get(new ProbeId($probeIdMatched[1]));

        $connections = $connectionRepository->byProbe($probe->id());
        $this->assertCount(2, $connections);

        $latestRows = $metricsRepository->latestPerConnection();
        $this->assertCount(2, $latestRows);

        $latestStatuses = [];
        $latestHealth = [];
        $now = time();
        $hasFreshUp = false;

        foreach ($latestRows as $row) {
            $latestStatuses[] = $row->status;
            $latestHealth[] = $row->healthy;

            if ($row->status === 'completed' && $row->completedAtUnix >= ($now - 3600)) {
                $hasFreshUp = true;
            }
        }

        $this->assertContains('completed', $latestStatuses);
        $this->assertTrue(
            $hasFreshUp,
            'At least one connection must have a fresh completed measurement for netpulse_up=1.',
        );

        $this->assertNotContains(
            null,
            $latestHealth,
            "Every seeded connection's newest measurement must have an evaluated health verdict.",
        );
        $this->assertContains(true, $latestHealth, 'At least one seeded connection must be healthy.');
        $this->assertContains(false, $latestHealth, 'At least one seeded connection must be unhealthy/degraded.');

        $allMeasurements = $entityManager->getRepository(Measurement::class)->findAll();
        $allStatuses = array_map(static fn(Measurement $m): MeasurementStatus => $m->status(), $allMeasurements);
        $this->assertContains(MeasurementStatus::Completed, $allStatuses);
        $this->assertContains(MeasurementStatus::Failed, $allStatuses);
        $this->assertGreaterThanOrEqual(24, count($allMeasurements));
    }
}
