<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Notification\Application\Digest\DigestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlDigestRepositoryTest extends KernelTestCase
{
    private const string PROBE_ID = '11111111-1111-1111-1111-111111111111';
    private const string CONN_A = '22222222-2222-2222-2222-222222222222';
    private const string CONN_B = '33333333-3333-3333-3333-333333333333';

    private DbalConnection $db;
    private DigestRepository $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(DigestRepository::class);
        $this->seed();
    }

    public function testAggregatesPerConnectionOverTheWindow(): void
    {
        $digests = $this->readModel->since(new DateTimeImmutable('2026-06-05 12:00:00'));

        $byConnection = [];

        foreach ($digests as $digest) {
            $byConnection[$digest->connectionName] = $digest;
        }

        self::assertArrayHasKey('wan1', $byConnection);
        self::assertArrayHasKey('wan2', $byConnection);

        $wan1 = $byConnection['wan1'];
        self::assertSame('home', $wan1->probeName);
        self::assertSame(4, $wan1->testsCount);
        self::assertSame(1, $wan1->failuresCount);

        self::assertSame(200_000_000, $wan1->avgDownloadBits);

        self::assertSame(20_000_000, $wan1->avgUploadBits);

        self::assertEqualsWithDelta(20.0, $wan1->avgPingMs, 1e-9);

        self::assertEqualsWithDelta(0.02, $wan1->avgPacketLossRatio, 1e-9);

        self::assertEqualsWithDelta(0.5, $wan1->healthyRatio, 1e-9);

        $wan2 = $byConnection['wan2'];
        self::assertSame(1, $wan2->testsCount);
        self::assertSame(0, $wan2->failuresCount);
        self::assertSame(500_000_000, $wan2->avgDownloadBits);
        self::assertSame(50_000_000, $wan2->avgUploadBits);
        self::assertEqualsWithDelta(5.0, $wan2->avgPingMs, 1e-9);
        self::assertEqualsWithDelta(1.0, $wan2->healthyRatio, 1e-9);
    }

    public function testWindowLowerBoundExcludesOlderRows(): void
    {
        $digests = $this->readModel->since(new DateTimeImmutable('2026-06-05 13:30:00'));

        $names = [];

        foreach ($digests as $digest) {
            $names[] = $digest->connectionName;
        }

        self::assertSame(['wan1'], $names);
    }

    public function testEmptyWindowReturnsEmptyCollection(): void
    {
        $digests = $this->readModel->since(new DateTimeImmutable('2030-01-01 00:00:00'));

        self::assertTrue($digests->isEmpty());
    }

    private function seed(): void
    {
        $this->db->insert('probes', [
            'id' => self::PROBE_ID,
            'name' => 'home',
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->insertConnection(self::CONN_A, 'wan1');
        $this->insertConnection(self::CONN_B, 'wan2');

        $this->insertMeasurement(
            'a0000000-0000-0000-0000-000000000000',
            self::CONN_A,
            'completed',
            '2026-06-05 09:00:00',
            999_000_000,
            99_000_000,
            99.0,
            0.99,
            true,
        );

        $this->insertMeasurement(
            'a0000000-0000-0000-0000-000000000001',
            self::CONN_A,
            'completed',
            '2026-06-05 13:00:00',
            100_000_000,
            10_000_000,
            10.0,
            0.0,
            true,
        );
        $this->insertMeasurement(
            'a0000000-0000-0000-0000-000000000002',
            self::CONN_A,
            'completed',
            '2026-06-05 14:00:00',
            200_000_000,
            20_000_000,
            20.0,
            0.02,
            true,
        );
        $this->insertMeasurement(
            'a0000000-0000-0000-0000-000000000003',
            self::CONN_A,
            'completed',
            '2026-06-05 15:00:00',
            300_000_000,
            30_000_000,
            30.0,
            0.04,
            false,
        );
        $this->insertMeasurement(
            'a0000000-0000-0000-0000-000000000004',
            self::CONN_A,
            'failed',
            '2026-06-05 16:00:00',
            null,
            null,
            null,
            null,
            null,
        );

        $this->insertMeasurement(
            'b0000000-0000-0000-0000-000000000001',
            self::CONN_B,
            'completed',
            '2026-06-05 13:00:00',
            500_000_000,
            50_000_000,
            5.0,
            0.0,
            true,
        );
    }

    private function insertConnection(string $id, string $name): void
    {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => self::PROBE_ID,
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
        string $id,
        string $connectionId,
        string $status,
        string $completedAt,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $ping,
        ?float $packetLossRatio,
        ?bool $healthy,
    ): void {
        $this->db->insert(
            'measurements',
            [
                'id' => $id,
                'probe_id' => self::PROBE_ID,
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
                'healthy' => $healthy,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );
    }
}
