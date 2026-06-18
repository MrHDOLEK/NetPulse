<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\ReadModel\ConnectionOverview;
use App\Dashboard\Application\ReadModel\ConnectionOverviewCollection;
use App\Dashboard\Application\ReadModel\ConnectionOverviewRepository;
use App\Dashboard\Application\ReadModel\Enum\ConnectionStatus;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class SqlConnectionOverviewRepositoryTest extends KernelTestCase
{
    private const string NOW = '2026-06-07 12:00:00';
    private const string PROBE_HOME = '11111111-1111-1111-1111-111111111111';
    private const string PROBE_OFFICE = '22222222-2222-2222-2222-222222222222';
    private const string CONN_HEALTHY = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CONN_DEGRADED = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const string CONN_DOWN = 'cccccccc-0000-0000-0000-000000000001';
    private const string CONN_EMPTY = 'dddddddd-0000-0000-0000-000000000001';

    private DbalConnection $db;
    private ConnectionOverviewRepository $readModel;
    private int $nowUnix;
    private int $measurementSeq = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $now = new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
        $this->nowUnix = $now->getTimestamp();

        $container->set(ClockInterface::class, new MockClock($now));

        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(ConnectionOverviewRepository::class);

        $this->insertProbe(self::PROBE_HOME, 'home');
        $this->insertProbe(self::PROBE_OFFICE, 'office');

        $this->insertConnection(self::CONN_HEALTHY, self::PROBE_HOME, 'wan1');
        $this->insertConnection(self::CONN_DEGRADED, self::PROBE_HOME, 'wan2');
        $this->insertConnection(self::CONN_DOWN, self::PROBE_OFFICE, 'wan3');
        $this->insertConnection(self::CONN_EMPTY, self::PROBE_OFFICE, 'wan4');
    }

    public function testEveryConnectionAppearsIncludingOnesWithoutMeasurements(): void
    {
        $overview = $this->readModel->overview(SeriesRange::Day);

        self::assertCount(4, $overview);

        $names = [];

        foreach ($overview as $item) {
            $names[] = $item->name;
        }

        self::assertSame(['wan1', 'wan2', 'wan3', 'wan4'], $names);
    }

    public function testHealthyConnectionAggregatesSnapshotAndStatus(): void
    {
        $since = $this->nowUnix - SeriesRange::Day->windowSeconds();

        $this->insert(self::CONN_HEALTHY, 'completed', $since + 10, 100, 10, 50.0, 5.0, 0.01, false);
        $this->insert(self::CONN_HEALTHY, 'failed', $since + 20, null, null, null, null, null, null);
        $this->insert(self::CONN_HEALTHY, 'completed', $since + 30, 200, 20, 30.0, 3.0, 0.00, true);
        $this->insert(self::CONN_HEALTHY, 'completed', $since + 40, 300, 30, 20.0, 2.0, 0.00, true);
        $this->insert(self::CONN_HEALTHY, 'completed', $since + 50, 400, 40, 10.0, 1.0, 0.00, true);

        $this->insert(self::CONN_HEALTHY, 'completed', $since - 100, 999, 99, 999.0, 99.0, 0.99, false);

        $item = $this->byName($this->readModel->overview(SeriesRange::Day))['wan1'];

        self::assertSame(5, $item->testsRun);
        self::assertSame(2, $item->incidents);
        self::assertEqualsWithDelta(60.0, $item->uptimePct, 1e-9);

        self::assertSame(ConnectionStatus::Healthy, $item->status);
        self::assertTrue($item->latestHealthy);

        self::assertSame(400, $item->downloadBits);
        self::assertSame(40, $item->uploadBits);

        self::assertEqualsWithDelta(0.010, $item->pingSeconds, 1e-9);
        self::assertEqualsWithDelta(0.001, $item->jitterSeconds, 1e-9);
        self::assertEqualsWithDelta(0.0, $item->packetLossRatio, 1e-9);
        self::assertSame($since + 50, $item->completedAtUnix);
        self::assertSame('Acme Speedtest', $item->serverName);
        self::assertSame('Warsaw', $item->serverLocation);
        self::assertSame('Acme ISP', $item->isp);
    }

    public function testDegradedConnectionWhenLatestCompletedButUnhealthy(): void
    {
        $since = $this->nowUnix - SeriesRange::Day->windowSeconds();

        $this->insert(self::CONN_DEGRADED, 'completed', $since + 10, 100, 10, 80.0, 8.0, 0.10, true);
        $this->insert(self::CONN_DEGRADED, 'completed', $since + 20, 50, 5, 200.0, 20.0, 0.20, false);

        $item = $this->byName($this->readModel->overview(SeriesRange::Day))['wan2'];

        self::assertSame(ConnectionStatus::Degraded, $item->status);
        self::assertSame(2, $item->testsRun);

        self::assertSame(1, $item->incidents);

        self::assertEqualsWithDelta(50.0, $item->uptimePct, 1e-9);

        self::assertSame(50, $item->downloadBits);
        self::assertFalse($item->latestHealthy);
    }

    public function testDownConnectionWhenLatestMeasurementFailed(): void
    {
        $since = $this->nowUnix - SeriesRange::Day->windowSeconds();

        $this->insert(self::CONN_DOWN, 'completed', $since + 10, 100, 10, 20.0, 2.0, 0.00, true);
        $this->insert(self::CONN_DOWN, 'failed', $since + 20, null, null, null, null, null, null);

        $item = $this->byName($this->readModel->overview(SeriesRange::Day))['wan3'];

        self::assertSame(ConnectionStatus::Down, $item->status);
        self::assertSame(2, $item->testsRun);

        self::assertSame(1, $item->incidents);

        self::assertEqualsWithDelta(50.0, $item->uptimePct, 1e-9);

        self::assertSame(100, $item->downloadBits);
        self::assertTrue($item->latestHealthy);
    }

    public function testEmptyConnectionHasZeroAggregatesNullSnapshotAndDownStatus(): void
    {
        $item = $this->byName($this->readModel->overview(SeriesRange::Day))['wan4'];

        self::assertSame(0, $item->testsRun);
        self::assertSame(0, $item->incidents);
        self::assertEqualsWithDelta(0.0, $item->uptimePct, 1e-9);
        self::assertSame(ConnectionStatus::Down, $item->status);

        self::assertNull($item->downloadBits);
        self::assertNull($item->uploadBits);
        self::assertNull($item->pingSeconds);
        self::assertNull($item->jitterSeconds);
        self::assertNull($item->packetLossRatio);
        self::assertNull($item->completedAtUnix);
        self::assertNull($item->latestHealthy);
        self::assertSame('', $item->serverName);
        self::assertSame('', $item->serverLocation);
    }

    public function testOtherConnectionRowsDoNotBleedIntoAConnectionsCounts(): void
    {
        $since = $this->nowUnix - SeriesRange::Day->windowSeconds();

        $this->insert(self::CONN_HEALTHY, 'completed', $since + 10, 100, 10, 20.0, 2.0, 0.00, true);
        $this->insert(self::CONN_DEGRADED, 'failed', $since + 10, null, null, null, null, null, null);
        $this->insert(self::CONN_DEGRADED, 'failed', $since + 20, null, null, null, null, null, null);
        $this->insert(self::CONN_DEGRADED, 'failed', $since + 30, null, null, null, null, null, null);

        $byName = $this->byName($this->readModel->overview(SeriesRange::Day));

        self::assertSame(1, $byName['wan1']->testsRun);
        self::assertSame(0, $byName['wan1']->incidents);
        self::assertEqualsWithDelta(100.0, $byName['wan1']->uptimePct, 1e-9);

        self::assertSame(3, $byName['wan2']->testsRun);
        self::assertSame(3, $byName['wan2']->incidents);
        self::assertEqualsWithDelta(0.0, $byName['wan2']->uptimePct, 1e-9);

        self::assertSame(ConnectionStatus::Down, $byName['wan2']->status);
    }

    public function testWindowLowerBoundExcludesOlderRows(): void
    {
        $since = $this->nowUnix - SeriesRange::Day->windowSeconds();

        $this->insert(self::CONN_HEALTHY, 'completed', $since + 5, 100, 10, 20.0, 2.0, 0.00, true);
        $this->insert(self::CONN_HEALTHY, 'completed', $since - 5, 100, 10, 20.0, 2.0, 0.00, true);

        $item = $this->byName($this->readModel->overview(SeriesRange::Day))['wan1'];

        self::assertSame(1, $item->testsRun);
    }

    /**
     * @return array<string, ConnectionOverview>
     */
    private function byName(ConnectionOverviewCollection $overview): array
    {
        $byName = [];

        foreach ($overview as $item) {
            $byName[$item->name] = $item;
        }

        return $byName;
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

    private function insertConnection(string $id, string $probeId, string $name): void
    {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => $probeId,
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

    private function insert(
        string $connectionId,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingMs,
        ?float $jitterMs,
        ?float $packetLossRatio,
        ?bool $healthy,
    ): void {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $probeId = $this->probeFor($connectionId);
        $sequence = ++$this->measurementSeq;

        $this->db->insert(
            'measurements',
            [
                'id' => sprintf('eeeeeeee-0000-0000-0000-%012d', $sequence),
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
                'ping' => $pingMs,
                'jitter' => $jitterMs,
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

    private function probeFor(string $connectionId): string
    {
        return match ($connectionId) {
            self::CONN_DOWN, self::CONN_EMPTY => self::PROBE_OFFICE,
            default => self::PROBE_HOME,
        };
    }
}
