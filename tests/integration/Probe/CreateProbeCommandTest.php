<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe;

use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateProbeCommandTest extends KernelTestCase
{
    private CommandTester $tester;
    private ProbeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(ProbeRepository::class);
        self::assertInstanceOf(ProbeRepository::class, $repository);
        $this->repository = $repository;

        $command = (new Application(self::$kernel))->find("app:probe:create");
        $this->tester = new CommandTester($command);
    }

    public function testCreatesProbePrintsTokenAndPersists(): void
    {
        $exitCode = $this->tester->execute([
            "name" => "edge-warsaw",
            "--label" => ["site=home", "link=wan1"],
        ]);

        $this->assertSame(0, $exitCode);

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString("Probe created", $display);
        $this->assertMatchesRegularExpression(
            "/Probe ID:\s+[0-9a-fA-F-]{36}/",
            $display,
        );

        $this->assertSame(1, preg_match("/Probe ID:\s+([0-9a-fA-F-]{36})/", $display, $idMatch));
        $this->assertSame(1, preg_match("/Token:\s+(\S+)/", $display, $tokenMatch));

        $probe = $this->repository->get(new ProbeId($idMatch[1]));

        $this->assertSame("edge-warsaw", $probe->name());
        $this->assertSame(["site" => "home", "link" => "wan1"], $probe->labels()->all());
        $this->assertTrue($probe->isEnabled());

        $hasher = self::getContainer()->get(ProbeTokenHasher::class);
        self::assertInstanceOf(ProbeTokenHasher::class, $hasher);
        $this->assertTrue($probe->verifyToken($tokenMatch[1], $hasher));
    }

    public function testFailsOnMalformedLabel(): void
    {
        $exitCode = $this->tester->execute([
            "name" => "edge-warsaw",
            "--label" => ["broken-label-without-equals"],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("Invalid label", $this->tester->getDisplay());
    }
}
