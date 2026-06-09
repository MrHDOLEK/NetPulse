<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\ReadModel\ServerListItem;
use App\Dashboard\Application\ReadModel\ServerListRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

final class SqlServerListRepositoryTest extends KernelTestCase
{
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";

    private DbalConnection $db;
    private ServerListRepository $readModel;
    private int $measurementSeq = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->readModel = $container->get(ServerListRepository::class);
        $this->seed();
    }

    public function testAllReturnsOneItemPerDistinctNonEmptyServerOrderedByName(): void
    {
        $items = $this->readModel->all()->toArray();

        self::assertCount(2, $items);

        foreach ($items as $item) {
            self::assertInstanceOf(ServerListItem::class, $item);
            self::assertNotSame("", $item->serverId, "empty/failed serverId must be excluded");
        }

        $byId = [];

        foreach ($items as $item) {
            $byId[$item->serverId] = $item;
        }

        self::assertArrayHasKey("12345", $byId);
        self::assertArrayHasKey("67890", $byId);

        self::assertContains($byId["12345"]->name, ["Acme", "Acme Speedtest"]);
        self::assertSame("67890", $byId["67890"]->serverId);
        self::assertSame("Globe", $byId["67890"]->name);
        self::assertSame("Berlin", $byId["67890"]->location);

        $names = array_map(static fn(ServerListItem $item): string => $item->name, $items);
        self::assertSame($names, array_values($names), "result preserved");
        self::assertSame("Globe", $names[1]);
        self::assertStringStartsWith("Acme", $names[0]);
    }

    private function seed(): void
    {
        $this->insertProbe();
        $this->insertConnection();

        $now = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->getTimestamp();

        $this->insertMeasurement("completed", $now - 5 * 3600, "12345", "Acme", "Warsaw", true);
        $this->insertMeasurement("completed", $now - 4 * 3600, "12345", "Acme", "Warsaw", true);

        $this->insertMeasurement("completed", $now - 3 * 3600, "12345", "Acme Speedtest", "Warsaw, PL", true);

        $this->insertMeasurement("completed", $now - 2 * 3600, "67890", "Globe", "Berlin", true);

        $this->insertMeasurement("failed", $now - 30 * 60, "", "", "", false);
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
        string $status,
        int $completedAtUnix,
        string $serverId,
        string $serverName,
        string $serverLocation,
        bool $healthy,
    ): void {
        $completedAt = (new DateTimeImmutable("@" . $completedAtUnix))
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");

        $sequence = ++$this->measurementSeq;

        $this->db->insert("measurements", [
            "id" => sprintf("eeeeeeee-0000-0000-0000-%012d", $sequence),
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
            "download_bits" => $status === "completed" ? 900_000_000 : null,
            "upload_bits" => $status === "completed" ? 90_000_000 : null,
            "ping" => $status === "completed" ? 10.0 : null,
            "jitter" => $status === "completed" ? 1.0 : null,
            "packet_loss_ratio" => $status === "completed" ? 0.0 : null,
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
