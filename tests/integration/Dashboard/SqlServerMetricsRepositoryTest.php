<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRepository;
use App\Dashboard\Application\ReadModel\ServerMetricsRow;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

use function sprintf;

final class SqlServerMetricsRepositoryTest extends KernelTestCase
{
    private const string NOW = "2026-06-08 12:00:00";
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";

    private DbalConnection $db;
    private ServerMetricsRepository $readModel;
    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $now = new DateTimeImmutable(self::NOW, new DateTimeZone("UTC"));
        $container->set(ClockInterface::class, new MockClock($now));

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->readModel = $container->get(ServerMetricsRepository::class);

        $this->insertProbe();
        $this->insertConnection();
    }

    public function testAggregatesOneRowPerDistinctNonEmptyServer(): void
    {
        $this->seedWorld();

        $rows = $this->readModel->all(HeatmapWindow::Month)->toArray();

        self::assertCount(2, $rows);

        $a = $this->rowFor($rows, "A");
        $b = $this->rowFor($rows, "B");

        self::assertSame(3, $a->testCount); 
        self::assertSame(1, $a->healthyCount); 

        self::assertNotNull($a->avgDownloadBits);
        self::assertEqualsWithDelta(750_000_000.0, $a->avgDownloadBits, 1e-3); 

        self::assertNotNull($a->avgUploadBits);
        self::assertEqualsWithDelta(500_000_000.0, $a->avgUploadBits, 1e-3); 

        self::assertNotNull($a->avgPingSeconds);
        self::assertEqualsWithDelta(0.06, $a->avgPingSeconds, 1e-9); 

        self::assertNotNull($a->avgLossRatio);
        self::assertEqualsWithDelta(0.01, $a->avgLossRatio, 1e-9); 

        self::assertSame($this->mondayAt(11), $a->lastSeenUnix);

        self::assertContains($a->name, ["Acme Speedtest", "Acme RENAMED"]);
        self::assertSame("Warsaw", $a->location);

        self::assertSame(1, $b->testCount);
        self::assertSame(1, $b->healthyCount);
        self::assertNotNull($b->avgDownloadBits);
        self::assertEqualsWithDelta(300_000_000.0, $b->avgDownloadBits, 1e-3);
        self::assertNotNull($b->avgPingSeconds);
        self::assertEqualsWithDelta(0.02, $b->avgPingSeconds, 1e-9);
        self::assertSame($this->mondayAt(9, 30), $b->lastSeenUnix);
        self::assertSame("Globe CDN", $b->name);
        self::assertSame("Berlin", $b->location);
    }

    public function testEmptyServerRowsAreExcluded(): void
    {
        $this->insertMeasurement("", "failed", $this->mondayAt(9), null, null, null, null, null, "", "");

        self::assertCount(0, $this->readModel->all(HeatmapWindow::Month)->toArray());
    }

    public function testOldRowsOutsideTheWindowAreExcluded(): void
    {
        $old = (new DateTimeImmutable(self::NOW, new DateTimeZone("UTC")))->modify("-40 days")->getTimestamp();
        $this->insertMeasurement("A", "completed", $old, 999_000_000, 999_000_000, 10.0, 0.0, true, "Acme Speedtest", "Warsaw");

        $rowsMonth = $this->readModel->all(HeatmapWindow::Month)->toArray();
        self::assertCount(0, $rowsMonth);

        $rowsQuarter = $this->readModel->all(HeatmapWindow::Quarter)->toArray();
        self::assertCount(1, $rowsQuarter);
        self::assertSame("A", $rowsQuarter[0]->serverId);
        self::assertSame(1, $rowsQuarter[0]->testCount);
    }

    /**
     * @param list<ServerMetricsRow> $rows
     */
    private function rowFor(array $rows, string $serverId): ServerMetricsRow
    {
        foreach ($rows as $row) {
            if ($row->serverId === $serverId) {
                return $row;
            }
        }

        self::fail(sprintf("No row found for serverId=%s", $serverId));
    }

    private function seedWorld(): void
    {
        $this->insertMeasurement("A", "completed", $this->mondayAt(9), 900_000_000, 600_000_000, 50.0, 0.00, true, "Acme Speedtest", "Warsaw");
        $this->insertMeasurement("A", "completed", $this->mondayAt(10), 600_000_000, 400_000_000, 70.0, 0.02, false, "Acme Speedtest", "Warsaw");
        $this->insertMeasurement("A", "failed", $this->mondayAt(11), null, null, null, null, null, "Acme RENAMED", "Warsaw");

        $this->insertMeasurement("B", "completed", $this->mondayAt(9, 30), 300_000_000, 200_000_000, 20.0, 0.00, true, "Globe CDN", "Berlin");

        $this->insertMeasurement("", "failed", $this->mondayAt(8), null, null, null, null, null, "", "");

        $old = (new DateTimeImmutable(self::NOW, new DateTimeZone("UTC")))->modify("-40 days")->getTimestamp();
        $this->insertMeasurement("A", "completed", $old, 999_000_000, 999_000_000, 10.0, 0.00, true, "Acme Speedtest", "Warsaw");
    }

    private function mondayAt(int $hour, int $minute = 0): int
    {
        return (new DateTimeImmutable(
            sprintf("2026-06-08 %02d:%02d:00", $hour, $minute),
            new DateTimeZone("UTC"),
        ))->getTimestamp();
    }

    private function insertProbe(): void
    {
        $this->db->insert("probes", [
            "id" => self::PROBE,
            "name" => "home",
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "token_hash" => "x",
            "enabled" => 1,
            "created_at" => "2026-06-05 10:00:00",
        ]);
    }

    private function insertConnection(): void
    {
        $this->db->insert("connections", [
            "id" => self::CONN,
            "probe_id" => self::PROBE,
            "name" => "Fibre WAN Primary",
            "isp" => "Acme ISP",
            "expected_download_bits" => 1_000_000_000,
            "expected_upload_bits" => 500_000_000,
            "color" => "primary",
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "server_pool" => json_encode([], JSON_THROW_ON_ERROR),
            "schedule" => json_encode(["mode" => "even", "cronExpressions" => [], "testsPerDay" => 24, "jitterSeconds" => 120], JSON_THROW_ON_ERROR),
            "thresholds" => json_encode(["minDownloadRatio" => 0.7, "minUploadRatio" => 0.7, "maxPingMs" => 100, "maxJitterMs" => 50, "maxPacketLossRatio" => 0.05], JSON_THROW_ON_ERROR),
            "adaptive_policy" => json_encode(["adaptiveIntervalSeconds" => 300, "recoveryHealthyCount" => 3, "maxConsecutiveFailures" => 5], JSON_THROW_ON_ERROR),
            "enabled" => 1,
        ]);
    }

    private function insertMeasurement(
        string $serverId,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $ping,
        ?float $packetLossRatio,
        ?bool $healthy,
        string $serverName,
        string $serverLocation,
    ): void {
        $completedAt = (new DateTimeImmutable("@" . $completedAtUnix))
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");

        $this->db->insert("measurements", [
            "id" => sprintf("cccccccc-0000-0000-0000-%012d", ++$this->sequence),
            "probe_id" => self::PROBE,
            "connection_id" => self::CONN,
            "status" => $status,
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => $serverId,
            "server_name" => $serverName,
            "server_location" => $serverLocation,
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => $downloadBits,
            "upload_bits" => $uploadBits,
            "ping" => $ping,
            "packet_loss_ratio" => $packetLossRatio,
            "data_used_download" => 0,
            "data_used_upload" => 0,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "raw_payload" => json_encode([], JSON_THROW_ON_ERROR),
            "healthy" => $healthy,
        ], [
            "healthy" => Types::BOOLEAN,
        ]);
    }
}
