<?php

declare(strict_types=1);

namespace App\Tests\Integration\Connection;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateConnectionCommandTest extends KernelTestCase
{
    private const string PROBE_UUID = "cccccccc-cccc-7ccc-8ccc-cccccccccccc";

    private CommandTester $commandTester;
    private ConnectionRepository $connectionRepository;
    private ProbeRepository $probeRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->connectionRepository = $container->get(ConnectionRepository::class);
        $this->probeRepository = $container->get(ProbeRepository::class);

        $application = new Application(self::$kernel);
        $this->commandTester = new CommandTester($application->find("app:connection:create"));
    }

    public function testCreatesConnectionForExistingProbe(): void
    {
        $this->persistProbe();

        $exitCode = $this->commandTester->execute([
            "--probe" => self::PROBE_UUID,
            "name" => "Home WAN1",
            "--isp" => "Orange",
            "--download-mbps" => "300",
            "--upload-mbps" => "50",
            "--color" => "violet",
            "--labels" => "site=home,link=wan1",
            "--server-pool" => "frankfurt.example.net:8080,warsaw.example.net:8080",
        ]);

        $this->assertSame(0, $exitCode);

        $byProbe = $this->connectionRepository->byProbe(new ProbeId(self::PROBE_UUID));
        $this->assertCount(1, $byProbe);

        $connection = $byProbe->toArray()[0];
        $this->assertSame("Home WAN1", $connection->name());
        $this->assertSame("Orange", $connection->isp());
        $this->assertSame(300_000_000, $connection->expected()->expectedDownloadBits);
        $this->assertSame(50_000_000, $connection->expected()->expectedUploadBits);
        $this->assertSame(ConnectionColor::Violet, $connection->color());
        $this->assertSame(["site" => "home", "link" => "wan1"], $connection->labels()->all());
        $this->assertSame(
            ["frankfurt.example.net:8080", "warsaw.example.net:8080"],
            $connection->serverPool()->all(),
        );
        $this->assertTrue($connection->isEnabled());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString($connection->id()->toString(), $output);
    }

    public function testCreatesConnectionWithDefaults(): void
    {
        $this->persistProbe();

        $exitCode = $this->commandTester->execute([
            "--probe" => self::PROBE_UUID,
            "name" => "Backup LTE",
        ]);

        $this->assertSame(0, $exitCode);

        $connection = $this->connectionRepository->byProbe(new ProbeId(self::PROBE_UUID))->toArray()[0];
        $this->assertSame("Backup LTE", $connection->name());
        $this->assertSame("", $connection->isp());
        $this->assertSame(0, $connection->expected()->expectedDownloadBits);
        $this->assertSame(0, $connection->expected()->expectedUploadBits);
        $this->assertSame(ConnectionColor::Primary, $connection->color());
        $this->assertSame([], $connection->labels()->all());
        $this->assertTrue($connection->labels()->isEmpty());
        $this->assertSame([], $connection->serverPool()->all());
        $this->assertTrue($connection->serverPool()->isEmpty());
        $this->assertTrue($connection->isEnabled());
    }

    public function testFailsWhenProbeDoesNotExist(): void
    {
        $exitCode = $this->commandTester->execute([
            "--probe" => "dddddddd-dddd-7ddd-8ddd-dddddddddddd",
            "name" => "Orphan",
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("not found", $this->commandTester->getDisplay());
    }

    public function testFailsWhenColorIsInvalid(): void
    {
        $this->persistProbe();

        $exitCode = $this->commandTester->execute([
            "--probe" => self::PROBE_UUID,
            "name" => "Home WAN1",
            "--color" => "neon",
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("color", $this->commandTester->getDisplay());
    }

    private function persistProbe(): void
    {
        $this->probeRepository->save(new Probe(
            new ProbeId(self::PROBE_UUID),
            "edge-01",
            Labels::empty(),
            "hash",
            true,
            new DateTimeImmutable("2026-01-01T00:00:00+00:00"),
        ));
    }
}
