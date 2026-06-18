<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListItem;
use App\Dashboard\Application\ReadModel\MeasurementListItemCollection;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlMeasurementListRepositoryTest extends KernelTestCase
{
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN_A = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CONN_B = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const string SERVER_WARSAW = '100';
    private const string SERVER_BERLIN = '200';
    private const string WINDOW_START = '2026-06-01 00:00:00';
    private const string NOW = '2026-06-08 00:00:00';

    private DbalConnection $db;
    private MeasurementListRepository $readModel;
    private int $windowStartUnix;
    private int $nowUnix;
    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(MeasurementListRepository::class);

        $this->windowStartUnix = new DateTimeImmutable(self::WINDOW_START, new DateTimeZone('UTC'))->getTimestamp();
        $this->nowUnix = new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'))->getTimestamp();

        $this->seed();
    }

    public function testWindowFilterReturnsOnlyInWindowRowsNewestFirst(): void
    {
        $items = $this->readModel->list($this->windowFilter(), 50, 0, MeasurementSort::default());

        self::assertCount(12, $items);

        $previous = null;

        foreach ($items as $item) {
            self::assertInstanceOf(MeasurementListItem::class, $item);

            self::assertGreaterThanOrEqual($this->windowStartUnix, $item->completedAtUnix);
            self::assertLessThan($this->nowUnix, $item->completedAtUnix);

            if ($previous !== null) {
                self::assertLessThanOrEqual($previous, $item->completedAtUnix);
            }

            $previous = $item->completedAtUnix;
        }
    }

    public function testConnectionFilterReturnsOnlyThatConnection(): void
    {
        $filter = new MeasurementFilter(
            connection: new ConnectionId(self::CONN_B),
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertSame('wan2', $item->connectionName);
        }

        self::assertSame(count($items), $this->readModel->countMatching($filter));
    }

    public function testServerFilterReturnsOnlyThatServer(): void
    {
        $filter = new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: self::SERVER_BERLIN,
            status: null,
            healthy: null,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertSame('Berlin', $item->serverLocation);
        }
    }

    public function testStatusFilterReturnsOnlyFailed(): void
    {
        $filter = new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: MeasurementStatus::Failed,
            healthy: null,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertSame(MeasurementStatus::Failed, $item->status);
        }

        self::assertSame(count($items), $this->readModel->countMatching($filter));
    }

    public function testHealthyFilterReturnsOnlyUnhealthy(): void
    {
        $filter = new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: false,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertFalse($item->healthy);
        }
    }

    public function testScheduledFilterReturnsOnlyScheduled(): void
    {
        $filter = new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: true,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertTrue($item->scheduled);
        }
    }

    public function testCombinedFiltersNarrowCorrectly(): void
    {
        $filter = new MeasurementFilter(
            connection: new ConnectionId(self::CONN_A),
            since: $this->since(),
            until: $this->until(),
            serverId: self::SERVER_WARSAW,
            status: MeasurementStatus::Completed,
            healthy: null,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 50, 0, MeasurementSort::default());

        self::assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            self::assertSame('wan1', $item->connectionName);
            self::assertSame('Warsaw', $item->serverLocation);
            self::assertSame(MeasurementStatus::Completed, $item->status);
        }

        self::assertSame(count($items), $this->readModel->countMatching($filter));
    }

    public function testPaginationSlicesAndCountMatchingIsIndependentOfLimitOffset(): void
    {
        $filter = $this->windowFilter();

        $total = $this->readModel->countMatching($filter);
        self::assertSame(12, $total);

        $firstPage = $this->idsOf($this->readModel->list($filter, 5, 0, MeasurementSort::default()));
        $secondPage = $this->idsOf($this->readModel->list($filter, 5, 5, MeasurementSort::default()));
        $thirdPage = $this->idsOf($this->readModel->list($filter, 5, 10, MeasurementSort::default()));

        self::assertCount(5, $firstPage);
        self::assertCount(5, $secondPage);
        self::assertCount(2, $thirdPage);

        $allPaged = array_merge($firstPage, $secondPage, $thirdPage);
        self::assertCount(12, array_unique($allPaged));

        self::assertSame($total, $this->readModel->countMatching($filter));
    }

    public function testSortByDownloadDescOrdersByDownloadWithCompletedAtTiebreak(): void
    {
        $items = $this->readModel->list($this->windowFilter(), 50, 0, MeasurementSort::DownloadDesc);

        $previousDownload = null;
        $previousCompletedAt = null;

        foreach ($items as $item) {
            if ($previousDownload !== null) {
                self::assertLessThanOrEqual($previousDownload, $item->downloadBits ?? PHP_INT_MIN);

                if (($item->downloadBits ?? PHP_INT_MIN) === $previousDownload) {
                    self::assertLessThanOrEqual($previousCompletedAt, $item->completedAtUnix);
                }
            }

            $previousDownload = $item->downloadBits ?? PHP_INT_MIN;
            $previousCompletedAt = $item->completedAtUnix;
        }
    }

    public function testPingAndJitterAreConvertedFromMillisecondsToSeconds(): void
    {
        $unitsConnection = new ConnectionId(self::CONN_A);
        $unitsId = $this->insert(
            connectionId: self::CONN_A,
            serverId: self::SERVER_WARSAW,
            serverLocation: 'Warsaw',
            status: 'completed',
            completedAtUnix: $this->nowUnix - 42,
            downloadBits: 123_456_789,
            uploadBits: 12_345_678,
            pingMs: 50.0,
            jitterMs: 10.0,
            packetLossRatio: 0.02,
            healthy: true,
            scheduled: true,
        );

        $filter = new MeasurementFilter(
            connection: $unitsConnection,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        $byId = [];

        foreach ($this->readModel->list($filter, 50, 0, MeasurementSort::default()) as $item) {
            $byId[$item->id->toString()] = $item;
        }

        $row = $byId[$unitsId];
        self::assertInstanceOf(MeasurementId::class, $row->id);
        self::assertEqualsWithDelta(0.05, $row->pingSeconds, 1e-9);
        self::assertEqualsWithDelta(0.01, $row->jitterSeconds, 1e-9);

        self::assertSame(123_456_789, $row->downloadBits);
        self::assertSame(12_345_678, $row->uploadBits);
        self::assertEqualsWithDelta(0.02, $row->packetLossRatio, 1e-9);
    }

    public function testProjectedConnectionNameColorAndIspComeFromTheJoin(): void
    {
        $filter = new MeasurementFilter(
            connection: new ConnectionId(self::CONN_B),
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        $items = $this->readModel->list($filter, 1, 0, MeasurementSort::default());
        self::assertCount(1, $items);

        $item = $items->toArray()[0];
        self::assertSame('wan2', $item->connectionName);
        self::assertSame(ConnectionColor::Violet, $item->connectionColor);
        self::assertSame('Beta ISP', $item->isp);
    }

    private function windowFilter(): MeasurementFilter
    {
        return new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );
    }

    private function since(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::WINDOW_START, new DateTimeZone('UTC'));
    }

    private function until(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
    }

    /**
     * @return list<string>
     */
    private function idsOf(MeasurementListItemCollection $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $ids[] = $item->id->toString();
        }

        return $ids;
    }

    private function seed(): void
    {
        $this->insertProbe(self::PROBE, 'home');
        $this->insertConnection(self::CONN_A, 'wan1', 'primary', 'Alpha ISP');
        $this->insertConnection(self::CONN_B, 'wan2', 'violet', 'Beta ISP');

        $start = $this->windowStartUnix;

        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 100,
            900_000_000,
            90_000_000,
            12.0,
            2.0,
            0.00,
            true,
            true,
        );
        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 200,
            800_000_000,
            80_000_000,
            14.0,
            3.0,
            0.01,
            true,
            true,
        );
        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 300,
            700_000_000,
            70_000_000,
            16.0,
            4.0,
            0.00,
            false,
            true,
        );

        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 400,
            500_000_000,
            50_000_000,
            20.0,
            5.0,
            0.02,
            true,
            true,
        );
        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 500,
            500_000_000,
            50_000_000,
            22.0,
            6.0,
            0.02,
            true,
            true,
        );

        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'failed',
            $start + 600,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
        );

        $this->insert(
            self::CONN_A,
            self::SERVER_BERLIN,
            'Berlin',
            'completed',
            $start + 700,
            300_000_000,
            30_000_000,
            60.0,
            12.0,
            0.08,
            false,
            false,
        );

        $this->insert(
            self::CONN_B,
            self::SERVER_BERLIN,
            'Berlin',
            'completed',
            $start + 800,
            600_000_000,
            60_000_000,
            18.0,
            4.0,
            0.01,
            true,
            true,
        );
        $this->insert(
            self::CONN_B,
            self::SERVER_BERLIN,
            'Berlin',
            'completed',
            $start + 900,
            650_000_000,
            65_000_000,
            19.0,
            5.0,
            0.00,
            true,
            true,
        );

        $this->insert(
            self::CONN_B,
            self::SERVER_BERLIN,
            'Berlin',
            'failed',
            $start + 1000,
            null,
            null,
            null,
            null,
            null,
            null,
            true,
        );

        $this->insert(
            self::CONN_B,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 1100,
            400_000_000,
            40_000_000,
            70.0,
            15.0,
            0.09,
            false,
            false,
        );
        $this->insert(
            self::CONN_B,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 1200,
            420_000_000,
            42_000_000,
            24.0,
            7.0,
            0.01,
            true,
            false,
        );

        $this->insert(
            self::CONN_A,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start - 50,
            999_000_000,
            99_000_000,
            9.0,
            1.0,
            0.00,
            true,
            true,
        );

        $this->insert(
            self::CONN_B,
            self::SERVER_BERLIN,
            'Berlin',
            'completed',
            $this->nowUnix,
            999_000_000,
            99_000_000,
            9.0,
            1.0,
            0.00,
            true,
            true,
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

    private function insert(
        string $connectionId,
        string $serverId,
        string $serverLocation,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingMs,
        ?float $jitterMs,
        ?float $packetLossRatio,
        ?bool $healthy,
        bool $scheduled,
    ): string {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $id = sprintf('eeeeeeee-0000-0000-0000-%012d', ++$this->sequence);

        $this->db->insert(
            'measurements',
            [
                'id' => $id,
                'probe_id' => self::PROBE,
                'connection_id' => $connectionId,
                'status' => $status,
                'scheduled' => $scheduled ? 1 : 0,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'server_id' => $serverId,
                'server_name' => 'Acme Speedtest',
                'server_location' => $serverLocation,
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

        return $id;
    }
}
