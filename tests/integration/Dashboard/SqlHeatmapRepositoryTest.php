<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use App\Dashboard\Application\ReadModel\HeatmapRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class SqlHeatmapRepositoryTest extends KernelTestCase
{
    private const string NOW = "2026-06-08 12:00:00";
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";
    private const string OTHER_CONN = "bbbbbbbb-0000-0000-0000-000000000001";

    private DbalConnection $db;
    private HeatmapRepository $readModel;
    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $now = new DateTimeImmutable(self::NOW, new DateTimeZone("UTC"));

        $container->set(ClockInterface::class, new MockClock($now));

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->readModel = $container->get(HeatmapRepository::class);

        $this->insertProbe();
        $this->insertConnection(self::CONN, "wan1");
        $this->insertConnection(self::OTHER_CONN, "wan2");
    }

    public function testDownloadAveragesNonNullsWithFailureCountedAsAttempt(): void
    {
        $hour = 10;
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 1), 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 2), 700_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 3), 800_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "failed", $this->mondayAt($hour, 4), null, null, false);

        $cell = $this->cell(HeatmapMetric::Download, 0, $hour);

        self::assertSame(800_000_000.0, $cell->value);
        self::assertSame(3, $cell->samples);
        self::assertSame(4, $cell->attempts);
    }

    public function testPingIsDividedByOneThousandInSql(): void
    {
        $hour = 11;
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 1), 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 2), 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 3), 900_000_000, 50.0, true);

        $cell = $this->cell(HeatmapMetric::Ping, 0, $hour);

        self::assertNotNull($cell->value);
        self::assertEqualsWithDelta(0.05, $cell->value, 1e-9);
        self::assertSame(3, $cell->samples);
    }

    public function testHealthRatioCountsFailuresInTheDenominator(): void
    {
        $hour = 8;
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 1), 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 2), 800_000_000, 60.0, true);
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 3), 100_000_000, 90.0, false);
        $this->insertMeasurement(self::CONN, "failed", $this->mondayAt($hour, 4), null, null, false);

        $cell = $this->cell(HeatmapMetric::Health, 0, $hour);

        self::assertNotNull($cell->value);
        self::assertEqualsWithDelta(0.5, $cell->value, 1e-9); 
        self::assertSame(4, $cell->attempts);
        self::assertSame(4, $cell->samples);
    }

    public function testOtherConnectionsRowsAreExcluded(): void
    {
        $hour = 10;
        $this->insertMeasurement(self::OTHER_CONN, "completed", $this->mondayAt($hour, 1), 999_000_000, 50.0, true);
        $this->insertMeasurement(self::OTHER_CONN, "completed", $this->mondayAt($hour, 2), 999_000_000, 50.0, true);
        $this->insertMeasurement(self::OTHER_CONN, "completed", $this->mondayAt($hour, 3), 999_000_000, 50.0, true);

        $cell = $this->cell(HeatmapMetric::Download, 0, $hour);

        self::assertNull($cell->value);
        self::assertSame(0, $cell->samples);
        self::assertSame(0, $cell->attempts);
    }

    public function testMeasurementsOlderThanThirtyDaysAreExcludedForMonthWindow(): void
    {
        $oldUnix = (new DateTimeImmutable(self::NOW, new DateTimeZone("UTC")))
            ->modify("-40 days")->getTimestamp();
        $this->insertMeasurement(self::CONN, "completed", $oldUnix, 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $oldUnix + 60, 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $oldUnix + 120, 900_000_000, 50.0, true);

        $w = (int)gmdate("w", $oldUnix);
        $dow = ($w + 6) % 7;
        $oldHour = (int)gmdate("G", $oldUnix);

        $cell = $this->cell(HeatmapMetric::Download, $dow, $oldHour);

        self::assertNull($cell->value);
        self::assertSame(0, $cell->attempts);
    }

    public function testWindowIsHalfOpenAtSinceInclusiveAndNowExclusive(): void
    {
        $now = new DateTimeImmutable(self::NOW, new DateTimeZone("UTC"));
        $sinceUnix = $now->modify("-" . HeatmapWindow::Month->windowSeconds() . " seconds")->getTimestamp();
        $nowUnix = $now->getTimestamp();

        $this->insertMeasurement(self::CONN, "completed", $sinceUnix, 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $nowUnix, 900_000_000, 50.0, true);

        $sinceDow = ((int)gmdate("w", $sinceUnix) + 6) % 7;
        $sinceHour = (int)gmdate("G", $sinceUnix);
        $nowDow = ((int)gmdate("w", $nowUnix) + 6) % 7;
        $nowHour = (int)gmdate("G", $nowUnix);

        self::assertSame(1, $this->cell(HeatmapMetric::Download, $sinceDow, $sinceHour)->attempts);

        self::assertSame(0, $this->cell(HeatmapMetric::Download, $nowDow, $nowHour)->attempts);
    }

    public function testQuarterWindowReachesBackFartherThanMonth(): void
    {
        $now = new DateTimeImmutable(self::NOW, new DateTimeZone("UTC"));
        $fortyDaysUnix = $now->modify("-40 days")->getTimestamp();
        $hundredDaysUnix = $now->modify("-100 days")->getTimestamp();

        $this->insertMeasurement(self::CONN, "completed", $fortyDaysUnix, 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "completed", $hundredDaysUnix, 900_000_000, 50.0, true);

        $fortyDow = ((int)gmdate("w", $fortyDaysUnix) + 6) % 7;
        $fortyHour = (int)gmdate("G", $fortyDaysUnix);
        $hundredDow = ((int)gmdate("w", $hundredDaysUnix) + 6) % 7;
        $hundredHour = (int)gmdate("G", $hundredDaysUnix);

        self::assertSame(
            1,
            $this->cellForWindow(HeatmapMetric::Download, HeatmapWindow::Quarter, $fortyDow, $fortyHour)->attempts,
        );

        self::assertSame(
            0,
            $this->cellForWindow(HeatmapMetric::Download, HeatmapWindow::Quarter, $hundredDow, $hundredHour)->attempts,
        );
    }

    public function testGridAlwaysHas168CellsAndEmptySlotsAreNull(): void
    {
        $grid = $this->readModel->grid(
            new HeatmapQuery(HeatmapMetric::Download, HeatmapWindow::Month, new ConnectionId(self::CONN)),
        );

        self::assertCount(168, $grid);

        foreach ($grid as $cell) {
            self::assertNull($cell->value);
            self::assertSame(0, $cell->samples);
            self::assertSame(0, $cell->attempts);
        }
    }

    public function testSingleSampleSlotReportsItsValue(): void
    {
        $hour = 9;
        $this->insertMeasurement(self::CONN, "completed", $this->mondayAt($hour, 1), 900_000_000, 50.0, true);
        $this->insertMeasurement(self::CONN, "failed", $this->mondayAt($hour, 2), null, null, false);

        $cell = $this->cell(HeatmapMetric::Download, 0, $hour);

        self::assertSame(900_000_000.0, $cell->value);
        self::assertSame(1, $cell->samples);
        self::assertSame(2, $cell->attempts);
    }

    private function mondayAt(int $hour, int $minute = 0): int
    {
        return (new DateTimeImmutable(
            sprintf("2026-06-08 %02d:%02d:00", $hour, $minute),
            new DateTimeZone("UTC"),
        ))->getTimestamp();
    }

    private function cell(HeatmapMetric $metric, int $dow, int $hour): HeatmapCell
    {
        return $this->cellForWindow($metric, HeatmapWindow::Month, $dow, $hour);
    }

    private function cellForWindow(HeatmapMetric $metric, HeatmapWindow $window, int $dow, int $hour): HeatmapCell
    {
        $grid = $this->readModel->grid(
            new HeatmapQuery($metric, $window, new ConnectionId(self::CONN)),
        );

        foreach ($grid as $cell) {
            if ($cell->dow === $dow && $cell->hour === $hour) {
                return $cell;
            }
        }

        self::fail(sprintf("No cell found for dow=%d hour=%d", $dow, $hour));
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

    private function insertConnection(string $id, string $name): void
    {
        $this->db->insert("connections", [
            "id" => $id,
            "probe_id" => self::PROBE,
            "name" => $name,
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
        string $connectionId,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?float $ping,
        ?bool $healthy,
    ): void {
        $completedAt = (new DateTimeImmutable("@" . $completedAtUnix))
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");

        $this->db->insert("measurements", [
            "id" => sprintf("dddddddd-0000-0000-0000-%012d", ++$this->sequence),
            "probe_id" => self::PROBE,
            "connection_id" => $connectionId,
            "status" => $status,
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => $downloadBits,
            "upload_bits" => $downloadBits,
            "ping" => $ping,
            "packet_loss_ratio" => null,
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
