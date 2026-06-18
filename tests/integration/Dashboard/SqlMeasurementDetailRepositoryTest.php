<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Dashboard\Application\ReadModel\MeasurementDetail;
use App\Dashboard\Application\ReadModel\MeasurementDetailRepository;
use App\Dashboard\Application\ReadModel\MeasurementNotFound;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlMeasurementDetailRepositoryTest extends KernelTestCase
{
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string COMPLETED_ID = 'eeeeeeee-0000-0000-0000-000000000001';
    private const string FAILED_ID = 'eeeeeeee-0000-0000-0000-000000000002';
    private const string AGENT_FAILED_ID = 'eeeeeeee-0000-0000-0000-000000000003';
    private const string UNKNOWN_ID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    private const string COMPLETED_AT = '2026-06-05 12:00:00';
    private const string STARTED_AT = '2026-06-05 11:59:55';

    private DbalConnection $db;
    private MeasurementDetailRepository $readModel;
    private int $completedAtUnix;
    private int $startedAtUnix;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(MeasurementDetailRepository::class);

        $this->completedAtUnix = new DateTimeImmutable(self::COMPLETED_AT, new DateTimeZone('UTC'))->getTimestamp();
        $this->startedAtUnix = new DateTimeImmutable(self::STARTED_AT, new DateTimeZone('UTC'))->getTimestamp();

        $this->seed();
    }

    public function testCompletedMeasurementProjectsEveryFieldWithLatencyInSeconds(): void
    {
        $detail = $this->readModel->get(new MeasurementId(self::COMPLETED_ID));

        self::assertInstanceOf(MeasurementDetail::class, $detail);

        self::assertInstanceOf(MeasurementId::class, $detail->id);
        self::assertSame(self::COMPLETED_ID, $detail->id->toString());
        self::assertSame($this->completedAtUnix, $detail->completedAtUnix);
        self::assertSame($this->startedAtUnix, $detail->startedAtUnix);

        self::assertSame('wan1', $detail->connectionName);
        self::assertSame(ConnectionColor::Primary, $detail->connectionColor);
        self::assertSame('Acme ISP', $detail->isp);

        self::assertSame('100', $detail->serverId);
        self::assertSame('Acme Speedtest', $detail->serverName);
        self::assertSame('Warsaw', $detail->serverLocation);
        self::assertSame('speedtest.acme.example:8080', $detail->serverHost);

        self::assertTrue($detail->scheduled);
        self::assertSame(MeasurementStatus::Completed, $detail->status);
        self::assertNull($detail->failReason);
        self::assertTrue($detail->healthy);

        self::assertSame(900_000_000, $detail->downloadBits);
        self::assertSame(90_000_000, $detail->uploadBits);
        self::assertEqualsWithDelta(0.02, $detail->packetLossRatio, 1e-9);
        self::assertSame(123_456, $detail->dataUsedDownload);
        self::assertSame(7_890, $detail->dataUsedUpload);
        self::assertSame('https://www.speedtest.net/result/c/result-1', $detail->resultUrl);

        self::assertEqualsWithDelta(0.05, $detail->pingSeconds, 1e-9);
        self::assertEqualsWithDelta(0.04, $detail->pingLowSeconds, 1e-9);
        self::assertEqualsWithDelta(0.06, $detail->pingHighSeconds, 1e-9);
        self::assertEqualsWithDelta(0.01, $detail->jitterSeconds, 1e-9);
        self::assertEqualsWithDelta(0.025, $detail->downloadLatencyIqmSeconds, 1e-9);
        self::assertEqualsWithDelta(0.035, $detail->uploadLatencyIqmSeconds, 1e-9);

        self::assertSame('result', $detail->rawPayload['type'] ?? null);
        self::assertSame('12345', $detail->rawPayload['serverId'] ?? null);
    }

    public function testFailedMeasurementExtractsFailReasonFromRawPayloadErrorKey(): void
    {
        $detail = $this->readModel->get(new MeasurementId(self::FAILED_ID));

        self::assertSame(MeasurementStatus::Failed, $detail->status);

        self::assertSame('timeout', $detail->failReason);

        self::assertNull($detail->downloadBits);
        self::assertNull($detail->uploadBits);
        self::assertNull($detail->pingSeconds);
        self::assertNull($detail->jitterSeconds);
        self::assertNull($detail->packetLossRatio);
        self::assertNull($detail->healthy);

        self::assertSame('error', $detail->rawPayload['type'] ?? null);
        self::assertSame('timeout', $detail->rawPayload['error'] ?? null);
    }

    public function testFailedMeasurementFallsBackToRawPayloadMessageForAgentSynthesizedFailures(): void
    {
        $detail = $this->readModel->get(new MeasurementId(self::AGENT_FAILED_ID));

        self::assertSame(MeasurementStatus::Failed, $detail->status);

        self::assertSame('speedtest binary timed out', $detail->failReason);

        self::assertSame('error', $detail->rawPayload['type'] ?? null);
        self::assertSame('speedtest binary timed out', $detail->rawPayload['message'] ?? null);
        self::assertNull($detail->rawPayload['error'] ?? null);
    }

    public function testUnknownIdRaisesMeasurementNotFound(): void
    {
        $this->expectException(MeasurementNotFound::class);

        $this->readModel->get(new MeasurementId(self::UNKNOWN_ID));
    }

    private function seed(): void
    {
        $this->insertProbe(self::PROBE, 'home');
        $this->insertConnection(self::CONN, 'wan1', 'primary', 'Acme ISP');

        $this->db->insert(
            'measurements',
            [
                'id' => self::COMPLETED_ID,
                'probe_id' => self::PROBE,
                'connection_id' => self::CONN,
                'status' => 'completed',
                'scheduled' => 1,
                'started_at' => self::STARTED_AT,
                'completed_at' => self::COMPLETED_AT,
                'server_id' => '100',
                'server_name' => 'Acme Speedtest',
                'server_location' => 'Warsaw',
                'server_host' => 'speedtest.acme.example:8080',
                'isp' => 'Acme ISP',
                'download_bits' => 900_000_000,
                'upload_bits' => 90_000_000,
                'download_bytes' => 112_500_000,
                'upload_bytes' => 11_250_000,
                'ping' => 50.0,
                'ping_low' => 40.0,
                'ping_high' => 60.0,
                'jitter' => 10.0,
                'download_jitter' => 5.0,
                'upload_jitter' => 6.0,
                'download_latency_iqm' => 25.0,
                'download_latency_low' => 20.0,
                'download_latency_high' => 30.0,
                'upload_latency_iqm' => 35.0,
                'upload_latency_low' => 28.0,
                'upload_latency_high' => 44.0,
                'packet_loss_ratio' => 0.02,
                'data_used_download' => 123_456,
                'data_used_upload' => 7_890,
                'download_elapsed' => 4000,
                'upload_elapsed' => 4000,
                'result_url' => 'https://www.speedtest.net/result/c/result-1',
                'raw_payload' => json_encode([
                    'type' => 'result',
                    'serverId' => '12345',
                    'ping' => ['latency' => 50.0, 'jitter' => 10.0],
                ], JSON_THROW_ON_ERROR),
                'healthy' => true,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );

        $this->db->insert(
            'measurements',
            [
                'id' => self::FAILED_ID,
                'probe_id' => self::PROBE,
                'connection_id' => self::CONN,
                'status' => 'failed',
                'scheduled' => 0,
                'started_at' => self::STARTED_AT,
                'completed_at' => self::COMPLETED_AT,
                'server_id' => '',
                'server_name' => '',
                'server_location' => '',
                'server_host' => '',
                'isp' => '',
                'download_bits' => null,
                'upload_bits' => null,
                'download_bytes' => null,
                'upload_bytes' => null,
                'ping' => null,
                'ping_low' => null,
                'ping_high' => null,
                'jitter' => null,
                'download_jitter' => null,
                'upload_jitter' => null,
                'download_latency_iqm' => null,
                'download_latency_low' => null,
                'download_latency_high' => null,
                'upload_latency_iqm' => null,
                'upload_latency_low' => null,
                'upload_latency_high' => null,
                'packet_loss_ratio' => null,
                'data_used_download' => 0,
                'data_used_upload' => 0,
                'download_elapsed' => 0,
                'upload_elapsed' => 0,
                'result_url' => null,
                'raw_payload' => json_encode([
                    'type' => 'error',
                    'error' => 'timeout',
                ], JSON_THROW_ON_ERROR),
                'healthy' => null,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );

        $this->db->insert(
            'measurements',
            [
                'id' => self::AGENT_FAILED_ID,
                'probe_id' => self::PROBE,
                'connection_id' => self::CONN,
                'status' => 'failed',
                'scheduled' => 0,
                'started_at' => self::STARTED_AT,
                'completed_at' => self::COMPLETED_AT,
                'server_id' => '',
                'server_name' => '',
                'server_location' => '',
                'server_host' => '',
                'isp' => '',
                'download_bits' => null,
                'upload_bits' => null,
                'download_bytes' => null,
                'upload_bytes' => null,
                'ping' => null,
                'ping_low' => null,
                'ping_high' => null,
                'jitter' => null,
                'download_jitter' => null,
                'upload_jitter' => null,
                'download_latency_iqm' => null,
                'download_latency_low' => null,
                'download_latency_high' => null,
                'upload_latency_iqm' => null,
                'upload_latency_low' => null,
                'upload_latency_high' => null,
                'packet_loss_ratio' => null,
                'data_used_download' => 0,
                'data_used_upload' => 0,
                'download_elapsed' => 0,
                'upload_elapsed' => 0,
                'result_url' => null,
                'raw_payload' => json_encode([
                    'type' => 'error',
                    'message' => 'speedtest binary timed out',
                ], JSON_THROW_ON_ERROR),
                'healthy' => null,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );
    }

    private function insertProbe(string $id, string $name): void
    {
        $this->db->insert('probes', [
            'id' => $id,
            'name' => $name,
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);
    }

    private function insertConnection(string $id, string $name, string $color, string $isp): void
    {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => self::PROBE,
            'name' => $name,
            'isp' => $isp,
            'expected_download_bits' => 1_000_000_000,
            'expected_upload_bits' => 500_000_000,
            'color' => $color,
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
}
