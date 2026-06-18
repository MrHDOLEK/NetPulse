<?php

declare(strict_types=1);

namespace App\Tests\Integration\Metrics;

use App\Metrics\Application\MetricsRepository;
use App\Metrics\Application\ReadModel\DegradedRow;
use App\Metrics\Application\ReadModel\UnhealthyCountRow;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlMetricsRepositoryTest extends KernelTestCase
{
    private DbalConnection $db;
    private MetricsRepository $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(MetricsRepository::class);
        $this->seed();
    }

    public function testLatestPerConnectionReturnsNewestCompletedRowPerConnection(): void
    {
        $rows = $this->readModel->latestPerConnection();

        self::assertCount(2, $rows);

        $byConnection = [];

        foreach ($rows as $row) {
            $byConnection[$row->connectionName] = $row;
        }

        $fresh = $byConnection['wan1'];
        self::assertSame('home', $fresh->probeName);
        self::assertSame('home-lab', $fresh->site);
        self::assertSame(950000000, $fresh->downloadBits);
        self::assertSame(480000000, $fresh->uploadBits);

        self::assertEqualsWithDelta(0.012, $fresh->pingSeconds, 1e-9);
        self::assertEqualsWithDelta(0.0021, $fresh->jitterSeconds, 1e-9);
        self::assertEqualsWithDelta(0.015, $fresh->downloadLatencyIqmSeconds, 1e-9);

        self::assertSame(123456789, $fresh->dataUsedBytes);
        self::assertSame('12345', $fresh->serverId);
        self::assertSame('Acme Speedtest', $fresh->serverName);
        self::assertSame('Acme ISP', $fresh->isp);

        self::assertTrue($fresh->healthy);

        $stale = $byConnection['wan2'];
        self::assertSame(100000000, $stale->downloadBits);

        self::assertSame(strtotime('2020-01-01 00:00:00 UTC'), $stale->completedAtUnix);

        self::assertFalse($stale->healthy);
    }

    public function testRunCountsGroupByProbeConnectionAndStatus(): void
    {
        $counts = $this->readModel->runCounts();

        $key = static fn(string $conn, string $status): string => $conn . ':' . $status;
        $byKey = [];

        foreach ($counts as $row) {
            $byKey[$key($row->connectionName, $row->status)] = $row->count;
        }

        self::assertSame(2, $byKey['wan1:completed']);
        self::assertSame(1, $byKey['wan1:failed']);
        self::assertSame(2, $byKey['wan2:completed']);
    }

    public function testUnhealthyCountsCountConnectionsBelowThreshold(): void
    {
        $counts = $this->readModel->unhealthyCounts();

        $byConnection = [];

        foreach ($counts as $row) {
            $byConnection[$row->connectionName] = $row;
        }

        self::assertArrayNotHasKey('wan1', $byConnection);

        self::assertArrayHasKey('wan2', $byConnection);
        $wan2 = $byConnection['wan2'];
        self::assertInstanceOf(UnhealthyCountRow::class, $wan2);
        self::assertSame('home', $wan2->probeName);
        self::assertSame(2, $wan2->count);
    }

    public function testConnectionDegradedUsesTheSharedDecider(): void
    {
        $degraded = $this->readModel->connectionDegraded();

        $byConnection = [];

        foreach ($degraded as $row) {
            self::assertInstanceOf(DegradedRow::class, $row);
            $byConnection[$row->connectionName] = $row;
        }

        self::assertArrayHasKey('wan1', $byConnection);
        self::assertFalse($byConnection['wan1']->degraded);

        self::assertArrayHasKey('wan2', $byConnection);
        self::assertTrue($byConnection['wan2']->degraded);
        self::assertSame('home', $byConnection['wan2']->probeName);
    }

    public function testRecentHealthHistoryIsNewestFirstAndTrimmed(): void
    {
        $degraded = $this->readModel->connectionDegraded();

        $wan2Degraded = null;

        foreach ($degraded as $row) {
            if ($row->connectionName === 'wan2') {
                $wan2Degraded = $row->degraded;
            }
        }

        self::assertTrue($wan2Degraded);
    }

    public function testConnectionsExpectedReturnsPlanSpeeds(): void
    {
        $expected = $this->readModel->connectionsExpected();

        $byConnection = [];

        foreach ($expected as $row) {
            $byConnection[$row->connectionName] = $row;
        }

        self::assertSame(1000000000, $byConnection['wan1']->expectedDownloadBits);
        self::assertSame(500000000, $byConnection['wan1']->expectedUploadBits);
        self::assertSame(600000000, $byConnection['wan2']->expectedDownloadBits);
    }

    public function testNotificationSendsGroupByKindChannelStatus(): void
    {
        $sends = $this->readModel->notificationSends();

        $byKey = [];

        foreach ($sends as $row) {
            $byKey[$row->kind . ':' . $row->channel . ':' . $row->status] = $row->total;
        }

        self::assertSame(2, $byKey['alert:webhook:sent']);
        self::assertSame(1, $byKey['recovery:webhook:sent']);
        self::assertSame(1, $byKey['digest:webhook:sent']);
        self::assertSame(1, $byKey['alert:email:failed']);

        self::assertCount(4, $sends);
    }

    private function seed(): void
    {
        $probeId = '11111111-1111-1111-1111-111111111111';
        $connFresh = '22222222-2222-2222-2222-222222222222';
        $connStale = '33333333-3333-3333-3333-333333333333';

        $this->db->insert('probes', [
            'id' => $probeId,
            'name' => 'home',
            'labels' => json_encode(['site' => 'home-lab'], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->db->insert('connections', [
            'id' => $connFresh,
            'probe_id' => $probeId,
            'name' => 'wan1',
            'isp' => 'Acme ISP',
            'expected_download_bits' => 1000000000,
            'expected_upload_bits' => 500000000,
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
        $this->db->insert('connections', [
            'id' => $connStale,
            'probe_id' => $probeId,
            'name' => 'wan2',
            'isp' => 'Acme ISP',
            'expected_download_bits' => 600000000,
            'expected_upload_bits' => 300000000,
            'color' => 'violet',
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

        $this->insertMeasurement(
            'aaaaaaaa-0000-0000-0000-000000000001',
            $probeId,
            $connFresh,
            'completed',
            '2026-06-05 11:00:00',
            900000000,
            400000000,
            11.0,
            2.0,
            0.0,
            14.0,
            17.0,
            60000000,
            40000000,
            true,
        );
        $this->insertMeasurement(
            'aaaaaaaa-0000-0000-0000-000000000002',
            $probeId,
            $connFresh,
            'completed',
            '2026-06-05 12:00:00',
            950000000,
            480000000,
            12.0,
            2.1,
            0.0,
            15.0,
            18.0,
            100000000,
            23456789,
            true,
        );

        $this->insertMeasurement(
            'aaaaaaaa-0000-0000-0000-000000000003',
            $probeId,
            $connFresh,
            'failed',
            '2026-06-05 11:30:00',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $this->insertMeasurement(
            'bbbbbbbb-0000-0000-0000-000000000001',
            $probeId,
            $connStale,
            'completed',
            '2019-12-31 23:00:00',
            100000000,
            50000000,
            30.0,
            5.0,
            0.01,
            40.0,
            45.0,
            30000000,
            20000000,
            false,
        );
        $this->insertMeasurement(
            'bbbbbbbb-0000-0000-0000-000000000002',
            $probeId,
            $connStale,
            'completed',
            '2020-01-01 00:00:00',
            100000000,
            50000000,
            30.0,
            5.0,
            0.01,
            40.0,
            45.0,
            30000000,
            20000000,
            false,
        );

        $this->db->executeStatement('DELETE FROM notification_send_counts');
        $this->insertSendCount('alert', 'webhook', 'sent', 2);
        $this->insertSendCount('recovery', 'webhook', 'sent', 1);
        $this->insertSendCount('digest', 'webhook', 'sent', 1);
        $this->insertSendCount('alert', 'email', 'failed', 1);
    }

    private function insertSendCount(string $kind, string $channel, string $status, int $total): void
    {
        $this->db->insert('notification_send_counts', [
            'kind' => $kind,
            'channel' => $channel,
            'status' => $status,
            'total' => $total,
        ]);
    }

    private function insertMeasurement(
        string $id,
        string $probeId,
        string $connectionId,
        string $status,
        string $completedAt,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $ping,
        ?float $jitter,
        ?float $packetLossRatio,
        ?float $downloadLatencyIqm,
        ?float $uploadLatencyIqm,
        ?int $dataUsedDownload,
        ?int $dataUsedUpload,
        ?bool $healthy = null,
    ): void {
        $this->db->insert(
            'measurements',
            [
                'id' => $id,
                'probe_id' => $probeId,
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
                'jitter' => $jitter,
                'download_latency_iqm' => $downloadLatencyIqm,
                'upload_latency_iqm' => $uploadLatencyIqm,
                'packet_loss_ratio' => $packetLossRatio,
                'data_used_download' => $dataUsedDownload ?? 0,
                'data_used_upload' => $dataUsedUpload ?? 0,
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
