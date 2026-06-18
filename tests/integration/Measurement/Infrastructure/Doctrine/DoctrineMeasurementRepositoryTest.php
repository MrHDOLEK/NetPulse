<?php

declare(strict_types=1);

namespace App\Tests\Integration\Measurement\Infrastructure\Doctrine;

use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineMeasurementRepositoryTest extends KernelTestCase
{
    public function testSavesAndReloadsCompletedMeasurement(): void
    {
        self::bootKernel();

        $id = new MeasurementId('44444444-4444-4444-8444-444444444444');
        $measurement = MeasurementMother::fromOoklaArray(
            [
                'type' => 'result',
                'ping' => ['latency' => 12.5, 'jitter' => 1.2, 'low' => 11.0, 'high' => 14.0],
                'download' => [
                    'bandwidth' => 117_875_000,
                    'bytes' => 1_200_000_000,
                    'elapsed' => 9_000,
                    'latency' => ['iqm' => 18.4],
                ],
                'upload' => [
                    'bandwidth' => 23_375_000,
                    'bytes' => 240_000_000,
                    'elapsed' => 8_000,
                    'latency' => ['iqm' => 22.1],
                ],
                'packetLoss' => 0.0,
                'isp' => 'Orange Polska',
                'server' => [
                    'id' => 12746,
                    'host' => 'speedtest.orange.pl',
                    'port' => 8080,
                    'name' => 'Orange Polska',
                    'location' => 'Warsaw',
                ],
                'result' => ['url' => 'https://www.speedtest.net/result/c/abc-123'],
            ],
            $id->toString(),
            '55555555-5555-4555-8555-555555555555',
            '66666666-6666-4666-8666-666666666666',
            true,
            new DateTimeImmutable('2026-06-06T10:00:00+00:00'),
        );

        $repository = $this->repository();
        $repository->save($measurement);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        $reloaded = $this->repository()->get($id);

        self::assertTrue($reloaded->id()->equals($id));
        self::assertSame(MeasurementStatus::Completed, $reloaded->status());
        self::assertNull($reloaded->healthy());

        $bandwidth = $reloaded->bandwidth();
        self::assertNotNull($bandwidth);
        self::assertSame(117_875_000 * 8, $bandwidth->downloadBits);

        $packetLoss = $reloaded->packetLoss();
        self::assertNotNull($packetLoss);
        self::assertSame(0.0, $packetLoss->ratio);

        self::assertSame('speedtest.orange.pl:8080', $reloaded->server()->serverHost);
        self::assertSame('https://www.speedtest.net/result/c/abc-123', $reloaded->resultUrl());

        $completedAt = $reloaded->completedAt();
        self::assertNotNull($completedAt);
        self::assertEquals(new DateTimeImmutable('2026-06-06T10:00:00+00:00'), $completedAt);
        self::assertSame('UTC', $completedAt->getTimezone()->getName());
    }

    public function testGetThrowsMeasurementNotFoundForUnknownId(): void
    {
        self::bootKernel();

        $this->expectException(MeasurementNotFound::class);

        $this->repository()->get(new MeasurementId('77777777-7777-4777-8777-777777777777'));
    }

    private function repository(): MeasurementRepository
    {
        $repository = self::getContainer()->get(MeasurementRepository::class);
        self::assertInstanceOf(MeasurementRepository::class, $repository);

        return $repository;
    }
}
