<?php

declare(strict_types=1);

namespace App\Tests\Integration\Measurement;

use App\Measurement\Application\PublicResult\PublicResult;
use App\Measurement\Application\PublicResult\PublicResultRepository;
use App\Measurement\Application\PublicResult\ResultNotFound;
use App\Measurement\Domain\Enum\MeasurementStatus;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlPublicResultRepositoryTest extends KernelTestCase
{
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";
    private const string SHARED_ID = "eeeeeeee-0000-0000-0000-000000000001";
    private const string SHARE_TOKEN = "abcdefghijklmnopqrstuvwxyz0123456789_-ABCDEF";
    private const string UNKNOWN_TOKEN = "ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ";
    private const string COMPLETED_AT = "2026-06-05 12:00:00";
    private const string STARTED_AT = "2026-06-05 11:59:55";

    private DbalConnection $db;
    private PublicResultRepository $readModel;
    private int $completedAtUnix;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->readModel = $container->get(PublicResultRepository::class);

        $this->completedAtUnix = (new DateTimeImmutable(self::COMPLETED_AT, new DateTimeZone("UTC")))->getTimestamp();

        $this->seed();
    }

    public function testKnownTokenProjectsOnlySafeFieldsWithLatencyInSeconds(): void
    {
        $result = $this->readModel->get(self::SHARE_TOKEN);

        self::assertInstanceOf(PublicResult::class, $result);

        self::assertSame(900_000_000, $result->downloadBits);
        self::assertSame(90_000_000, $result->uploadBits);

        self::assertEqualsWithDelta(0.05, $result->pingSeconds, 1e-9);   
        self::assertEqualsWithDelta(0.01, $result->jitterSeconds, 1e-9); 
        self::assertEqualsWithDelta(0.02, $result->lossRatio, 1e-9);

        self::assertSame("Acme Speedtest", $result->serverName);
        self::assertSame("Warsaw", $result->serverLocation);
        self::assertSame("Acme ISP", $result->isp);

        self::assertSame($this->completedAtUnix, $result->completedAtUnix);
        self::assertSame(MeasurementStatus::Completed, $result->status);
        self::assertTrue($result->healthy);
    }

    public function testUnknownTokenRaisesResultNotFound(): void
    {
        $this->expectException(ResultNotFound::class);

        $this->readModel->get(self::UNKNOWN_TOKEN);
    }

    public function testPublicResultVoExposesNoInternalIdentifiers(): void
    {
        $properties = array_map(
            static fn($property): string => $property->getName(),
            (new ReflectionClass(PublicResult::class))->getProperties(),
        );

        foreach (["probeId", "connectionId", "connectionName", "connection", "serverId", "serverHost", "resultUrl", "rawPayload"] as $forbidden) {
            self::assertNotContains($forbidden, $properties, "PublicResult must not expose " . $forbidden);
        }
    }

    private function seed(): void
    {
        $this->insertProbe(self::PROBE, "home");
        $this->insertConnection(self::CONN, "wan1", "primary", "Acme ISP");

        $this->db->insert("measurements", [
            "id" => self::SHARED_ID,
            "probe_id" => self::PROBE,
            "connection_id" => self::CONN,
            "status" => "completed",
            "scheduled" => 1,
            "started_at" => self::STARTED_AT,
            "completed_at" => self::COMPLETED_AT,
            "server_id" => "100",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => 900_000_000,
            "upload_bits" => 90_000_000,
            "download_bytes" => 112_500_000,
            "upload_bytes" => 11_250_000,
            "ping" => 50.0,
            "jitter" => 10.0,
            "packet_loss_ratio" => 0.02,
            "data_used_download" => 123_456,
            "data_used_upload" => 7_890,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "result_url" => "https://www.speedtest.net/result/c/result-1",
            "raw_payload" => json_encode(["type" => "result"], JSON_THROW_ON_ERROR),
            "healthy" => true,
            "share_token" => self::SHARE_TOKEN,
        ], [
            "healthy" => Types::BOOLEAN,
        ]);
    }

    private function insertProbe(string $id, string $name): void
    {
        $this->db->insert("probes", [
            "id" => $id,
            "name" => $name,
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "token_hash" => "x",
            "enabled" => 1,
            "created_at" => "2026-06-05 10:00:00",
        ]);
    }

    private function insertConnection(string $id, string $name, string $color, string $isp): void
    {
        $this->db->insert("connections", [
            "id" => $id,
            "probe_id" => self::PROBE,
            "name" => $name,
            "isp" => $isp,
            "expected_download_bits" => 1_000_000_000,
            "expected_upload_bits" => 500_000_000,
            "color" => $color,
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "server_pool" => json_encode([], JSON_THROW_ON_ERROR),
            "schedule" => json_encode(["mode" => "even", "cronExpressions" => [], "testsPerDay" => 24, "jitterSeconds" => 120], JSON_THROW_ON_ERROR),
            "thresholds" => json_encode(["minDownloadRatio" => 0.7, "minUploadRatio" => 0.7, "maxPingMs" => 100, "maxJitterMs" => 50, "maxPacketLossRatio" => 0.05], JSON_THROW_ON_ERROR),
            "adaptive_policy" => json_encode(["adaptiveIntervalSeconds" => 300, "recoveryHealthyCount" => 3, "maxConsecutiveFailures" => 5], JSON_THROW_ON_ERROR),
            "enabled" => 1,
        ]);
    }
}
