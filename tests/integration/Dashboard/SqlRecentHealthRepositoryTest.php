<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\RecentHealthRepository;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlRecentHealthRepositoryTest extends KernelTestCase
{
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string OTHER_CONN = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const int BASE_UNIX = 1_700_000_000;

    private DbalConnection $db;
    private RecentHealthRepository $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(RecentHealthRepository::class);

        $this->insertProbe();
        $this->insertConnection(self::CONN, 'wan1');
        $this->insertConnection(self::OTHER_CONN, 'wan2');
    }

    public function testRecentReturnsNewestLimitSamplesNewestFirst(): void
    {
        for ($index = 0; $index < 70; $index++) {
            $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + $index, true);
        }

        $samples = $this->readModel->recent(new ConnectionId(self::CONN), 60);

        $array = $samples->toArray();
        self::assertCount(60, $array);

        foreach ($array as $position => $sample) {
            self::assertInstanceOf(HealthSample::class, $sample);
            $expectedUnix = self::BASE_UNIX + (69 - $position);
            self::assertSame($expectedUnix, $sample->completedAt->getTimestamp());
        }
    }

    public function testRecentDefaultsToSixtyAndExcludesOlderMeasurements(): void
    {
        for ($index = 0; $index < 70; $index++) {
            $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + $index, true);
        }

        $samples = $this->readModel->recent(new ConnectionId(self::CONN));

        self::assertCount(60, $samples);
        self::assertSame(self::BASE_UNIX + 69, $samples->toArray()[0]->completedAt->getTimestamp());
        self::assertSame(self::BASE_UNIX + 10, $samples->toArray()[59]->completedAt->getTimestamp());
    }

    public function testRecentReturnsAllWhenFewerThanLimit(): void
    {
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 1, true);
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 2, false);
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 3, null);

        $samples = $this->readModel->recent(new ConnectionId(self::CONN), 60);

        self::assertCount(3, $samples);
    }

    public function testRecentMapsHealthyStatusAndFailureOntoEachSample(): void
    {
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 1, true);
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 2, false);
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 3, null);
        $this->insertMeasurement(self::CONN, 'failed', self::BASE_UNIX + 4, null);

        $array = $this->readModel->recent(new ConnectionId(self::CONN), 60)->toArray();
        self::assertCount(4, $array);

        self::assertTrue($array[0]->failed);
        self::assertNull($array[0]->healthy);
        self::assertTrue($array[0]->isUnhealthy());

        self::assertFalse($array[1]->failed);
        self::assertNull($array[1]->healthy);
        self::assertFalse($array[1]->isHealthy());
        self::assertFalse($array[1]->isUnhealthy());

        self::assertFalse($array[2]->failed);
        self::assertFalse($array[2]->healthy);
        self::assertTrue($array[2]->isUnhealthy());

        self::assertFalse($array[3]->failed);
        self::assertTrue($array[3]->healthy);
        self::assertTrue($array[3]->isHealthy());
    }

    public function testRecentExcludesOtherConnections(): void
    {
        $this->insertMeasurement(self::CONN, 'completed', self::BASE_UNIX + 1, true);
        $this->insertMeasurement(self::OTHER_CONN, 'completed', self::BASE_UNIX + 2, true);
        $this->insertMeasurement(self::OTHER_CONN, 'completed', self::BASE_UNIX + 3, false);

        $samples = $this->readModel->recent(new ConnectionId(self::CONN), 60);

        self::assertCount(1, $samples);
        self::assertSame(self::BASE_UNIX + 1, $samples->toArray()[0]->completedAt->getTimestamp());
    }

    public function testRecentReturnsEmptyWhenNoMeasurements(): void
    {
        $samples = $this->readModel->recent(new ConnectionId(self::CONN), 60);

        self::assertCount(0, $samples);
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

    private function insertMeasurement(string $connectionId, string $status, int $completedAtUnix, ?bool $healthy): void
    {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $this->db->insert(
            'measurements',
            [
                'id' => sprintf('dddddddd-0000-0000-%04d-%012d', $completedAtUnix % 10000, $completedAtUnix),
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
                'download_bits' => 100,
                'upload_bits' => 10,
                'ping' => 20.0,
                'packet_loss_ratio' => 0.0,
                'data_used_download' => 0,
                'data_used_upload' => 0,
                'download_elapsed' => 4000,
                'upload_elapsed' => 4000,
                'raw_payload' => json_encode([], JSON_THROW_ON_ERROR),
                'healthy' => $healthy,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );
    }
}
