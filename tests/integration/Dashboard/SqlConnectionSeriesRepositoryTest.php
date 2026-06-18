<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Bucketing\Bucketer;
use App\Dashboard\Application\ReadModel\ConnectionSeriesRepository;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\SeriesBucket;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class SqlConnectionSeriesRepositoryTest extends KernelTestCase
{
    private const string NOW = '2026-06-07 12:00:00';
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string OTHER_CONN = 'bbbbbbbb-0000-0000-0000-000000000001';

    private DbalConnection $db;
    private ConnectionSeriesRepository $readModel;
    private int $nowUnix;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $now = new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
        $this->nowUnix = $now->getTimestamp();

        $container->set(ClockInterface::class, new MockClock($now));

        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(ConnectionSeriesRepository::class);

        $this->insertProbe();
        $this->insertConnection(self::CONN, 'wan1');
        $this->insertConnection(self::OTHER_CONN, 'wan2');
    }

    /**
     * @return iterable<string, array{SeriesRange}>
     */
    public static function rangeProvider(): iterable
    {
        yield 'day' => [SeriesRange::Day];
        yield 'week' => [SeriesRange::Week];
        yield 'month' => [SeriesRange::Month];
        yield 'quarter' => [SeriesRange::Quarter];
    }

    #[DataProvider('rangeProvider')]
    public function testReturnsRangeBucketCountEquallySpacedAscendingUtc(SeriesRange $range): void
    {
        $result = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed);

        self::assertCount($range->buckets(), $result);

        $width = $range->bucketWidthSeconds();
        $since = $this->nowUnix - $range->windowSeconds();
        $buckets = $result->toArray();

        self::assertSame($since, $buckets[0]->bucketStart->getTimestamp());

        foreach ($buckets as $index => $bucket) {
            self::assertSame('UTC', $bucket->bucketStart->getTimezone()->getName());
            self::assertSame($since + ($index * $width), $bucket->bucketStart->getTimestamp());
        }

        $last = $buckets[$range->buckets() - 1];
        self::assertSame($this->nowUnix, $last->bucketStart->getTimestamp() + $width);
    }

    public function testMeasurementsInTheSameBucketAreAveraged(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        for ($i = 0; $i < Bucketer::RAW_POINT_THRESHOLD; $i++) {
            $this->insertMeasurement(self::CONN, 'completed', $since + 10 + $i, 200, 20, null, null);
        }

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray();

        self::assertCount($range->buckets(), $buckets);
        self::assertSame(200, $buckets[0]->downloadBits);
        self::assertSame(20, $buckets[0]->uploadBits);
        self::assertNull($buckets[1]->downloadBits);
    }

    public function testMeasurementsAcrossBucketsAreIndependent(): void
    {
        $range = SeriesRange::Day;
        $width = $range->bucketWidthSeconds();
        $since = $this->nowUnix - $range->windowSeconds();

        for ($i = 0; $i < Bucketer::RAW_POINT_THRESHOLD; $i++) {
            $this->insertMeasurement(self::CONN, 'completed', $since + 10 + $i, 100, 10, null, null);
            $this->insertMeasurement(self::CONN, 'completed', $since + (5 * $width) + 10 + $i, 500, 50, null, null);
        }

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray();

        self::assertSame(100, $buckets[0]->downloadBits);
        self::assertNull($buckets[3]->downloadBits);
        self::assertSame(500, $buckets[5]->downloadBits);
    }

    public function testSparseWindowIsReturnedAsRawPointsAtTrueTimestamps(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, 100, 10, null, null);
        $this->insertMeasurement(self::CONN, 'completed', $since + 20, 200, 20, null, null);
        $this->insertMeasurement(self::CONN, 'completed', $since + 30, 300, 30, null, null);

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray();

        self::assertCount(3, $buckets);
        self::assertSame(
            [$since + 10, $since + 20, $since + 30],
            array_map(static fn(SeriesBucket $bucket): int => $bucket->bucketStart->getTimestamp(), $buckets),
        );
        self::assertSame(
            [100, 200, 300],
            array_map(static fn(SeriesBucket $bucket): ?int => $bucket->downloadBits, $buckets),
        );
        self::assertSame('UTC', $buckets[0]->bucketStart->getTimezone()->getName());
    }

    public function testEmptyBucketsAreNull(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, null, null, 50.0, null);

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Ping)->toArray();

        self::assertEqualsWithDelta(0.05, $buckets[0]->pingSeconds, 1e-9);

        foreach (array_slice($buckets, 1) as $bucket) {
            self::assertNull($bucket->pingSeconds);
        }
    }

    public function testFailedRowsAreExcludedAndOnlyFailedBucketIsNull(): void
    {
        $range = SeriesRange::Day;
        $width = $range->bucketWidthSeconds();
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, 100, 10, null, null);
        $this->insertMeasurement(self::CONN, 'failed', $since + 20, null, null, null, null);

        $this->insertMeasurement(self::CONN, 'failed', $since + $width + 10, null, null, null, null);

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray();

        self::assertSame(100, $buckets[0]->downloadBits);
        self::assertNull($buckets[1]->downloadBits);
    }

    public function testSpeedMetricFillsDownloadAndUploadOnly(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, 100, 10, 50.0, 0.01);

        $bucket = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray()[0];

        self::assertSame(100, $bucket->downloadBits);
        self::assertSame(10, $bucket->uploadBits);
        self::assertNull($bucket->pingSeconds);
        self::assertNull($bucket->packetLossRatio);
    }

    public function testPingMetricFillsPingOnly(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, 100, 10, 50.0, 0.01);

        $bucket = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Ping)->toArray()[0];

        self::assertEqualsWithDelta(0.05, $bucket->pingSeconds, 1e-9);
        self::assertNull($bucket->downloadBits);
        self::assertNull($bucket->uploadBits);
        self::assertNull($bucket->packetLossRatio);
    }

    public function testLossMetricFillsPacketLossOnly(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 10, 100, 10, 50.0, 0.01);

        $bucket = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Loss)->toArray()[0];

        self::assertEqualsWithDelta(0.01, $bucket->packetLossRatio, 1e-9);
        self::assertNull($bucket->downloadBits);
        self::assertNull($bucket->uploadBits);
        self::assertNull($bucket->pingSeconds);
    }

    public function testTrendPctComparesCurrentToPreviousWindow(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $prevSince + 100, 100, 10, null, null);
        $this->insertMeasurement(self::CONN, 'completed', $prevSince + 200, 100, 10, null, null);
        $this->insertMeasurement(self::CONN, 'completed', $since + 100, 100, 10, null, null);
        $this->insertMeasurement(self::CONN, 'completed', $since + 200, 200, 20, null, null);

        $result = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed);

        self::assertNotNull($result->trendPct());
        self::assertEqualsWithDelta(50.0, $result->trendPct(), 1e-9);
    }

    public function testTrendPctNullWhenPreviousWindowEmpty(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $since + 100, 100, 10, null, null);

        $result = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed);

        self::assertNull($result->trendPct());
    }

    public function testTrendPctNullAndBucketsNullWhenCurrentWindowEmpty(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $this->insertMeasurement(self::CONN, 'completed', $prevSince + 100, 100, 10, null, null);

        $result = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed);

        self::assertNull($result->trendPct());

        foreach ($result as $bucket) {
            self::assertNull($bucket->downloadBits);
        }
    }

    public function testMeasurementsForOtherConnectionsAreExcluded(): void
    {
        $range = SeriesRange::Day;
        $since = $this->nowUnix - $range->windowSeconds();

        $this->insertMeasurement(self::OTHER_CONN, 'completed', $since + 10, 999, 99, null, null);

        $buckets = $this->readModel->series(new ConnectionId(self::CONN), $range, SeriesMetric::Speed)->toArray();

        foreach ($buckets as $bucket) {
            self::assertNull($bucket->downloadBits);
        }
    }

    private function insertProbe(): void
    {
        $this->db->insert('probes', [
            'id' => self::PROBE,
            'name' => 'home',
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);
    }

    private function insertConnection(string $id, string $name): void
    {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => self::PROBE,
            'name' => $name,
            'isp' => 'Acme ISP',
            'expected_download_bits' => 1_000_000_000,
            'expected_upload_bits' => 500_000_000,
            'color' => 'primary',
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'server_pool' => json_encode([], JSON_THROW_ON_ERROR),
            'schedule' => json_encode([
                'mode' => 'even',
                'cronExpressions' => [],
                'testsPerDay' => 24,
                'jitterSeconds' => 120,
            ], JSON_THROW_ON_ERROR),
            'thresholds' => json_encode([
                'minDownloadRatio' => 0.7,
                'minUploadRatio' => 0.7,
                'maxPingMs' => 100,
                'maxJitterMs' => 50,
                'maxPacketLossRatio' => 0.05,
            ], JSON_THROW_ON_ERROR),
            'adaptive_policy' => json_encode([
                'adaptiveIntervalSeconds' => 300,
                'recoveryHealthyCount' => 3,
                'maxConsecutiveFailures' => 5,
            ], JSON_THROW_ON_ERROR),
            'enabled' => 1,
        ]);
    }

    private function insertMeasurement(
        string $connectionId,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $ping,
        ?float $packetLossRatio,
    ): void {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $this->db->insert(
            'measurements',
            [
                'id' => sprintf('dddddddd-0000-0000-0000-%012d', $completedAtUnix % 1_000_000_000_000),
                'probe_id' => self::PROBE,
                'connection_id' => $connectionId,
                'status' => $status,
                'scheduled' => 1,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'server_id' => '12345',
                'server_name' => 'Acme Speedtest',
                'server_location' => 'Warsaw',
                'server_host' => 'speedtest.acme.example:8080',
                'isp' => 'Acme ISP',
                'download_bits' => $downloadBits,
                'upload_bits' => $uploadBits,
                'ping' => $ping,
                'packet_loss_ratio' => $packetLossRatio,
                'data_used_download' => 0,
                'data_used_upload' => 0,
                'download_elapsed' => 4000,
                'upload_elapsed' => 4000,
                'raw_payload' => json_encode([], JSON_THROW_ON_ERROR),
                'healthy' => null,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );
    }
}
