<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\ReadModel\DashboardCursorRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlDashboardCursorRepositoryTest extends KernelTestCase
{
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";
    private const int BASE_UNIX = 1_700_000_000;

    private DbalConnection $db;
    private DashboardCursorRepository $readModel;
    private int $measurementSeq = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->readModel = $container->get(DashboardCursorRepository::class);

        $this->insertProbe();
        $this->insertConnection();
    }

    public function testCurrentReturnsEmptyCursorWhenNoMeasurements(): void
    {
        $cursor = $this->readModel->current();

        self::assertNull($cursor->latestCompletedAtUnix);
        self::assertSame(0, $cursor->totalCount);
    }

    public function testCurrentCountsEveryMeasurementAndTakesTheMaxCompletedAt(): void
    {
        $this->insertMeasurement("completed", self::BASE_UNIX + 100);
        $this->insertMeasurement("completed", self::BASE_UNIX + 300);
        $this->insertMeasurement("failed", self::BASE_UNIX + 500);

        $cursor = $this->readModel->current();

        self::assertSame(3, $cursor->totalCount);
        self::assertSame(self::BASE_UNIX + 500, $cursor->latestCompletedAtUnix);
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
            "name" => "wan1",
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

    private function insertMeasurement(string $status, int $completedAtUnix): void
    {
        $completedAt = (new DateTimeImmutable("@" . $completedAtUnix))
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");

        $sequence = ++$this->measurementSeq;

        $this->db->insert("measurements", [
            "id" => sprintf("dddddddd-0000-0000-0000-%012d", $sequence),
            "probe_id" => self::PROBE,
            "connection_id" => self::CONN,
            "status" => $status,
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => 100,
            "upload_bits" => 10,
            "ping" => 20.0,
            "packet_loss_ratio" => 0.0,
            "data_used_download" => 0,
            "data_used_upload" => 0,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "raw_payload" => json_encode([], JSON_THROW_ON_ERROR),
            "healthy" => $status === "completed" ? true : null,
        ], [
            "healthy" => Types::BOOLEAN,
        ]);
    }
}
