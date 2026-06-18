<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\Service;

use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\ThresholdBreach;
use App\Measurement\Domain\Service\HealthEvaluator;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function intdiv;

final class HealthEvaluatorTest extends TestCase
{
    private const PROBE_ID = '22222222-2222-4222-8222-222222222222';
    private const CONNECTION_ID = '33333333-3333-4333-8333-333333333333';
    private const MEASUREMENT_ID = '99999999-9999-4999-8999-999999999999';
    private const EXPECTED_DOWNLOAD_BITS = 1_000_000_000;
    private const EXPECTED_UPLOAD_BITS = 500_000_000;

    /**
     * @return iterable<string, array{download: int, upload: int, ping: float, jitter: float, packetLossPercent: float, thresholds: Thresholds, expected: ExpectedSpeed, healthy: bool, breaches: list<ThresholdBreach>}>
     */
    public static function provideCompleted(): iterable
    {
        $thresholds = Thresholds::default();
        $expected = new ExpectedSpeed(self::EXPECTED_DOWNLOAD_BITS, self::EXPECTED_UPLOAD_BITS);

        $okDownload = (int) (0.9 * self::EXPECTED_DOWNLOAD_BITS);
        $okUpload = (int) (0.9 * self::EXPECTED_UPLOAD_BITS);

        yield 'all within thresholds is healthy' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'download exactly at the ratio is healthy' => [
            'download' => (int) (0.7 * self::EXPECTED_DOWNLOAD_BITS),
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'download just below the ratio breaches' => [
            'download' => (int) (0.7 * self::EXPECTED_DOWNLOAD_BITS) - 8,
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [ThresholdBreach::DownloadBelow],
        ];

        yield 'upload exactly at the ratio is healthy' => [
            'download' => $okDownload,
            'upload' => (int) (0.7 * self::EXPECTED_UPLOAD_BITS),
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'upload below the ratio breaches' => [
            'download' => $okDownload,
            'upload' => (int) (0.5 * self::EXPECTED_UPLOAD_BITS),
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [ThresholdBreach::UploadBelow],
        ];

        yield 'ping exactly at the cap is healthy' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 100.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'ping above the cap breaches' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 100.1,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [ThresholdBreach::PingHigh],
        ];

        yield 'jitter above the cap breaches' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 50.5,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [ThresholdBreach::JitterHigh],
        ];

        yield 'packet loss above the cap breaches' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 6.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [ThresholdBreach::PacketLossHigh],
        ];

        yield 'multiple breaches are all reported' => [
            'download' => (int) (0.4 * self::EXPECTED_DOWNLOAD_BITS),
            'upload' => (int) (0.4 * self::EXPECTED_UPLOAD_BITS),
            'ping' => 150.0,
            'jitter' => 80.0,
            'packetLossPercent' => 10.0,
            'thresholds' => $thresholds,
            'expected' => $expected,
            'healthy' => false,
            'breaches' => [
                ThresholdBreach::DownloadBelow,
                ThresholdBreach::UploadBelow,
                ThresholdBreach::PingHigh,
                ThresholdBreach::JitterHigh,
                ThresholdBreach::PacketLossHigh,
            ],
        ];

        yield 'null caps skip latency/jitter/loss checks' => [
            'download' => $okDownload,
            'upload' => $okUpload,
            'ping' => 999.0,
            'jitter' => 999.0,
            'packetLossPercent' => 99.0,
            'thresholds' => Thresholds::of(0.7, 0.7, null, null, null),
            'expected' => $expected,
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'expected download zero skips the download ratio breach' => [
            'download' => 1,
            'upload' => $okUpload,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => new ExpectedSpeed(0, self::EXPECTED_UPLOAD_BITS),
            'healthy' => true,
            'breaches' => [],
        ];

        yield 'expected upload zero skips the upload ratio breach' => [
            'download' => $okDownload,
            'upload' => 1,
            'ping' => 20.0,
            'jitter' => 5.0,
            'packetLossPercent' => 1.0,
            'thresholds' => $thresholds,
            'expected' => new ExpectedSpeed(self::EXPECTED_DOWNLOAD_BITS, 0),
            'healthy' => true,
            'breaches' => [],
        ];
    }

    /**
     * @param list<ThresholdBreach> $breaches
     */
    #[DataProvider('provideCompleted')]
    public function testEvaluatesCompletedMeasurements(
        int $download,
        int $upload,
        float $ping,
        float $jitter,
        float $packetLossPercent,
        Thresholds $thresholds,
        ExpectedSpeed $expected,
        bool $healthy,
        array $breaches,
    ): void {
        $measurement = $this->completedMeasurement($download, $upload, $ping, $jitter, $packetLossPercent);

        $verdict = new HealthEvaluator()->evaluate($measurement, $thresholds, $expected);

        self::assertSame($healthy, $verdict->isHealthy());
        self::assertSame(
            array_map(static fn(ThresholdBreach $breach): string => $breach->value, $breaches),
            array_map(static fn(ThresholdBreach $breach): string => $breach->value, $verdict->breaches()->toArray()),
        );
    }

    public function testFailedMeasurementIsUnhealthyWithTestFailedBreach(): void
    {
        $measurement = MeasurementMother::fromOoklaArray(
            ['type' => 'error', 'error' => 'boom'],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new DateTimeImmutable('2026-06-06T10:00:00+00:00'),
        );

        $verdict = new HealthEvaluator()->evaluate(
            $measurement,
            Thresholds::default(),
            new ExpectedSpeed(self::EXPECTED_DOWNLOAD_BITS, self::EXPECTED_UPLOAD_BITS),
        );

        self::assertFalse($verdict->isHealthy());
        self::assertSame([ThresholdBreach::TestFailed], $verdict->breaches()->toArray());
    }

    private function completedMeasurement(
        int $downloadBits,
        int $uploadBits,
        float $ping,
        float $jitter,
        float $packetLossPercent,
    ): Measurement {
        $payload = [
            'type' => 'result',
            'ping' => ['latency' => $ping, 'jitter' => $jitter, 'low' => $ping, 'high' => $ping],
            'download' => [
                'bandwidth' => intdiv($downloadBits, 8),
                'bytes' => 1_200_000_000,
                'elapsed' => 9_000,
                'latency' => ['iqm' => 18.4],
            ],
            'upload' => [
                'bandwidth' => intdiv($uploadBits, 8),
                'bytes' => 240_000_000,
                'elapsed' => 8_000,
                'latency' => ['iqm' => 22.1],
            ],
            'packetLoss' => $packetLossPercent,
            'isp' => 'Orange Polska',
            'server' => [
                'id' => 12746,
                'name' => 'Orange Polska',
                'location' => 'Warsaw',
                'host' => 'speedtest.orange.pl',
                'port' => 8080,
            ],
            'result' => ['url' => 'https://www.speedtest.net/result/c/abc-123'],
        ];

        return MeasurementMother::fromOoklaArray(
            $payload,
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new DateTimeImmutable('2026-06-06T10:00:00+00:00'),
        );
    }
}
