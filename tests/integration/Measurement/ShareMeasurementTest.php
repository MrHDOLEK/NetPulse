<?php

declare(strict_types=1);

namespace App\Tests\Integration\Measurement;

use App\Measurement\Application\Share\ShareMeasurement;
use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShareMeasurementTest extends KernelTestCase
{
    private const string MEASUREMENT_ID = "44444444-4444-4444-8444-444444444444";
    private const string PROBE_ID = "55555555-5555-4555-8555-555555555555";
    private const string CONNECTION_ID = "66666666-6666-4666-8666-666666666666";
    private const string UNKNOWN_ID = "77777777-7777-4777-8777-777777777777";

    public function testMintsPersistsAndIsIdempotent(): void
    {
        self::bootKernel();
        $this->persistMeasurement();

        $service = $this->service();
        $id = new MeasurementId(self::MEASUREMENT_ID);

        $token = $service($id);

        self::assertMatchesRegularExpression("/^[A-Za-z0-9_-]{43}$/", $token);

        $this->entityManager()->clear();
        self::assertSame($token, $this->repository()->get($id)->shareToken());

        $this->entityManager()->clear();
        self::assertSame($token, $service($id));
    }

    public function testUnknownIdRaisesMeasurementNotFound(): void
    {
        self::bootKernel();

        $this->expectException(MeasurementNotFound::class);

        ($this->service())(new MeasurementId(self::UNKNOWN_ID));
    }

    private function persistMeasurement(): void
    {
        $measurement = MeasurementMother::fromOoklaArray(
            [
                "type" => "result",
                "ping" => ["latency" => 12.5, "jitter" => 1.2],
                "download" => ["bandwidth" => 117_875_000, "bytes" => 1_200_000_000],
                "upload" => ["bandwidth" => 23_375_000, "bytes" => 240_000_000],
                "packetLoss" => 0.0,
                "isp" => "Orange Polska",
                "server" => ["id" => 12746, "host" => "speedtest.orange.pl", "port" => 8080, "name" => "Orange Polska", "location" => "Warsaw"],
            ],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new DateTimeImmutable("2026-06-06T10:00:00+00:00"),
        );

        $this->repository()->save($measurement);
    }

    private function service(): ShareMeasurement
    {
        return new ShareMeasurement($this->repository());
    }

    private function repository(): MeasurementRepository
    {
        $repository = self::getContainer()->get(MeasurementRepository::class);
        self::assertInstanceOf(MeasurementRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
