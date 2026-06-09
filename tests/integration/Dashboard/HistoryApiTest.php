<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function sprintf;

final class HistoryApiTest extends KernelTestCase
{
    private const string CSRF_TOKEN_ID = "authenticate";
    private const string RUN_TEST_TOKEN_ID = "run-test";
    private const string CSRF_RAW_TOKEN = "phpunit-login-token";
    private const string SHARE_CSRF_RAW_TOKEN = "phpunit-run-test-token";
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";
    private const string CONN_NAME = "Fibre WAN Primary";

    private DbalConnection $db;
    private MessageBusInterface $commandBus;
    private Session $session;
    private int $measurementSeq = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->commandBus = $container->get(MessageBusInterface::class);

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
    }

    public function testReturnsPaginatedListJsonWithRawAndLabelFields(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?limit=25&offset=0");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("application/json", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);

        self::assertArrayHasKey("items", $payload);
        self::assertArrayHasKey("total", $payload);
        self::assertSame(25, $payload["limit"]);
        self::assertSame(0, $payload["offset"]);
        self::assertIsArray($payload["items"]);

        self::assertSame(4, $payload["total"]);
        self::assertCount(4, $payload["items"]);

        $item = $payload["items"][0];

        foreach (["id", "t", "completedAt", "status", "connection", "color", "isp", "server", "location", "dl", "up", "ping", "jitter", "loss", "healthy", "scheduled"] as $field) {
            self::assertArrayHasKey($field, $item, "missing raw field: " . $field);
        }

        foreach (["downloadLabel", "uploadLabel", "pingLabel", "jitterLabel", "lossLabel", "statusLabel"] as $field) {
            self::assertArrayHasKey($field, $item, "missing label field: " . $field);
        }

        self::assertIsInt($item["t"]);
        self::assertSame(self::CONN_NAME, $item["connection"]);
        self::assertSame("primary", $item["color"]);

        $completed = $this->firstWithStatus($payload["items"], "completed");
        self::assertNotNull($completed, "expected at least one completed item");
        self::assertStringContainsString("Mbps", (string)$completed["downloadLabel"]);
        self::assertGreaterThan(100_000_000, $completed["dl"]);
        self::assertSame("Completed", $completed["statusLabel"]);
    }

    public function testStatusFilterNarrowsToFailed(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?status=failed");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);

        self::assertSame(1, $payload["total"]);
        self::assertCount(1, $payload["items"]);
        self::assertSame("failed", $payload["items"][0]["status"]);
    }

    public function testUntilDateBoundIncludesTheWholeUntilDay(): void
    {
        $this->seedWorld();
        $onUntilDay = (new DateTimeImmutable("2026-03-15 14:00:00", new DateTimeZone("UTC")))->getTimestamp();
        $this->insertMeasurement("completed", $onUntilDay, 900_000_000, 90_000_000, 10.0, 1.0, 0.0, true);
        $this->login();

        $response = $this->get("/dashboard/history?since=2026-03-15&until=2026-03-15");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);

        self::assertSame(1, $payload["total"]);
        self::assertCount(1, $payload["items"]);
        self::assertSame("completed", $payload["items"][0]["status"]);
    }

    public function testExportUntilDateBoundIncludesTheWholeUntilDay(): void
    {
        $this->seedWorld();
        $onUntilDay = (new DateTimeImmutable("2026-03-15 14:00:00", new DateTimeZone("UTC")))->getTimestamp();
        $this->insertMeasurement("completed", $onUntilDay, 900_000_000, 90_000_000, 10.0, 1.0, 0.0, true);
        $this->login();

        $response = $this->get("/dashboard/history/export.csv?since=2026-03-15&until=2026-03-15");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(StreamedResponse::class, $response);

        $lines = $this->streamLines($response);
        self::assertCount(2, $lines);
    }

    public function testRejectsLimitNotInAllowedSet(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?limit=7");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsUnknownSort(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?sort=nope");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsUnknownStatus(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?status=nope");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsInvalidConnectionUuid(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?connection=not-a-uuid");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsUnparseableSince(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history?since=nope");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get("/dashboard/history?limit=25&offset=0");

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testReturnsFullMeasurementDetailJson(): void
    {
        $this->seedWorld();
        $id = $this->insertFullMeasurement();
        $this->login();

        $response = $this->get("/dashboard/history/" . $id);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("application/json", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);

        self::assertSame($id, $payload["id"]);

        self::assertSame(self::CONN_NAME, $payload["connection"]);
        self::assertSame("primary", $payload["color"]);
        self::assertSame("Acme ISP", $payload["isp"]);
        self::assertSame("12345", $payload["serverId"]);
        self::assertSame("Acme Speedtest", $payload["serverName"]);
        self::assertSame("Warsaw", $payload["serverLocation"]);
        self::assertSame("speedtest.acme.example:8080", $payload["serverHost"]);

        self::assertSame("completed", $payload["status"]);
        self::assertSame("Completed", $payload["statusLabel"]);
        self::assertNull($payload["failReason"]);
        self::assertTrue($payload["scheduled"]);
        self::assertTrue($payload["healthy"]);
        self::assertSame("https://www.speedtest.net/result/c/abc", $payload["resultUrl"]);

        self::assertIsInt($payload["completedAtUnix"]);
        self::assertIsInt($payload["startedAtUnix"]);
        self::assertStringContainsString("T", (string)$payload["completedAt"]);
        self::assertStringContainsString("T", (string)$payload["startedAt"]);

        foreach (
            [
                "downloadBits", "uploadBits", "pingSeconds", "pingLowSeconds", "pingHighSeconds",
                "jitterSeconds", "downloadLatencyIqmSeconds", "uploadLatencyIqmSeconds", "packetLossRatio",
                "dataUsedDownload", "dataUsedUpload",
            ] as $field
        ) {
            self::assertArrayHasKey($field, $payload, "missing raw field: " . $field);
        }

        foreach (
            [
                "downloadLabel", "uploadLabel", "pingLabel", "pingLowLabel", "pingHighLabel",
                "jitterLabel", "downloadLatencyIqmLabel", "uploadLatencyIqmLabel", "lossLabel",
            ] as $field
        ) {
            self::assertArrayHasKey($field, $payload, "missing label field: " . $field);
        }

        self::assertGreaterThan(100_000_000, $payload["downloadBits"]);
        self::assertStringContainsString("Mbps", (string)$payload["downloadLabel"]);

        self::assertStringContainsString("ms", (string)$payload["pingLowLabel"]);
        self::assertStringContainsString("%", (string)$payload["lossLabel"]);

        self::assertArrayHasKey("rawPayload", $payload);
        self::assertIsArray($payload["rawPayload"]);
        self::assertSame("result", $payload["rawPayload"]["type"]);
    }

    public function testDetailRejectsNonUuidId(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history/abcdef00abcdef00abcdef00abcdef00abcd");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testDetailReturns404ForUnknownButValidUuid(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history/dddddddd-0000-0000-0000-000000000099");

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDetailRequiresAuthentication(): void
    {
        $this->seedWorld();
        $id = $this->insertFullMeasurement();

        $response = $this->get("/dashboard/history/" . $id);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testShareMintsAnOpaqueLinkAndIsIdempotent(): void
    {
        $this->seedWorld();
        $id = $this->insertFullMeasurement();
        $this->login();

        $response = $this->postShare($id, $this->validShareToken());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);
        self::assertArrayHasKey("shareUrl", $payload);
        self::assertMatchesRegularExpression("#^/r/[A-Za-z0-9_-]{43}$#", (string)$payload["shareUrl"]);

        $again = $this->postShare($id, $this->validShareToken());
        self::assertSame(Response::HTTP_OK, $again->getStatusCode());
        self::assertSame($payload["shareUrl"], $this->decode($again)["shareUrl"]);
    }

    public function testShareReturns404ForUnknownButValidUuid(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postShare("dddddddd-0000-0000-0000-000000000099", $this->validShareToken());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShareRejectsNonUuidId(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postShare("abcdef00abcdef00abcdef00abcdef00abcd", $this->validShareToken());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testShareRejectsBadCsrf(): void
    {
        $this->seedWorld();
        $id = $this->insertFullMeasurement();
        $this->login();

        $response = $this->postShare($id, "not-the-real-token");

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testShareRequiresAuthentication(): void
    {
        $this->seedWorld();
        $id = $this->insertFullMeasurement();

        $response = $this->postShare($id, $this->validShareToken());

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testExportsFilteredHistoryAsStreamedCsv(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history/export.csv?status=completed");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertStringContainsString("text/csv", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $disposition = (string)$response->headers->get("Content-Disposition");
        self::assertStringStartsWith('attachment; filename="netpulse-history-', $disposition);
        self::assertStringEndsWith('.csv"', $disposition);

        $lines = $this->streamLines($response);

        self::assertNotEmpty($lines);
        self::assertSame(
            "id,completed_at,connection_name,connection_isp,server_name,server_location,scheduled,download_mbps,upload_mbps,ping_ms,jitter_ms,packet_loss_pct,status,healthy,fail_reason",
            $lines[0],
        );

        $dataLines = array_slice($lines, 1);
        self::assertCount(3, $dataLines);

        foreach ($dataLines as $line) {
            $cells = str_getcsv($line, escape: "");
            self::assertSame("completed", $cells[12]);
        }
    }

    public function testExportRejectsUnparseableSince(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/history/export.csv?since=nope");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testExportRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get("/dashboard/history/export.csv");

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    /**
     * @return list<string>
     */
    private function streamLines(StreamedResponse $response): array
    {
        ob_start();
        $response->sendContent();
        $body = (string)ob_get_clean();

        return array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $body)), static fn(string $line): bool => $line !== ""));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function firstWithStatus(array $items, string $status): ?array
    {
        foreach ($items as $item) {
            if (($item["status"] ?? null) === $status) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function seedWorld(): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::ADMIN_EMAIL, self::ADMIN_PASSWORD));
        $this->insertProbe();
        $this->insertConnection();
        $this->seedMeasurements();
    }

    private function login(): void
    {
        $this->session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::CSRF_TOKEN_ID,
            self::CSRF_RAW_TOKEN,
        );

        $request = Request::create("/login", "POST", [
            "_username" => self::ADMIN_EMAIL,
            "_password" => self::ADMIN_PASSWORD,
            "_csrf_token" => self::CSRF_RAW_TOKEN,
        ]);
        $this->attachSession($request);

        self::getContainer()->get("kernel")->handle($request);
    }

    private function get(string $path): Response
    {
        $request = Request::create($path, "GET");
        $this->attachSession($request);

        return self::getContainer()->get("kernel")->handle($request);
    }

    private function validShareToken(): string
    {
        $this->session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::RUN_TEST_TOKEN_ID,
            self::SHARE_CSRF_RAW_TOKEN,
        );

        return self::SHARE_CSRF_RAW_TOKEN;
    }

    private function postShare(string $id, ?string $csrfToken): Response
    {
        $request = Request::create(
            sprintf("/dashboard/history/%s/share", $id),
            "POST",
            [],
            [],
            [],
            ["CONTENT_TYPE" => "application/json"],
            "",
        );

        if ($csrfToken !== null) {
            $request->headers->set("X-CSRF-Token", $csrfToken);
        }
        $this->attachSession($request);

        return self::getContainer()->get("kernel")->handle($request);
    }

    private function attachSession(Request $request): void
    {
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), $this->session->getId());
    }

    private function seedMeasurements(): void
    {
        $now = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->getTimestamp();

        $this->insertMeasurement("completed", $now - 3 * 3600, 920_000_000, 92_000_000, 12.0, 1.5, 0.0, true);
        $this->insertMeasurement("completed", $now - 2 * 3600, 940_000_000, 95_000_000, 11.0, 1.2, 0.0, true);
        $this->insertMeasurement("completed", $now - 1 * 3600, 955_000_000, 98_000_000, 9.5, 1.0, 0.0, true);
        $this->insertMeasurement("failed", $now - 30 * 60, null, null, null, null, null, false);
    }

    private function insertFullMeasurement(): string
    {
        $now = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->getTimestamp();
        $startedAt = (new DateTimeImmutable("@" . ($now - 3700)))->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d H:i:s");
        $completedAt = (new DateTimeImmutable("@" . ($now - 3600)))->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d H:i:s");

        $id = "cccccccc-0000-0000-0000-000000000777";

        $this->db->insert("measurements", [
            "id" => $id,
            "probe_id" => self::PROBE,
            "connection_id" => self::CONN,
            "status" => "completed",
            "scheduled" => 1,
            "started_at" => $startedAt,
            "completed_at" => $completedAt,
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => 955_000_000,
            "upload_bits" => 98_000_000,
            "ping" => 9.5,
            "ping_low" => 8.0,
            "ping_high" => 14.0,
            "jitter" => 1.0,
            "download_latency_iqm" => 22.0,
            "upload_latency_iqm" => 30.0,
            "packet_loss_ratio" => 0.01,
            "data_used_download" => 1_200_000_000,
            "data_used_upload" => 120_000_000,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "result_url" => "https://www.speedtest.net/result/c/abc",
            "raw_payload" => json_encode(["type" => "result", "ping" => ["latency" => 9.5]], JSON_THROW_ON_ERROR),
            "healthy" => true,
        ], [
            "healthy" => Types::BOOLEAN,
        ]);

        return $id;
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
            "name" => self::CONN_NAME,
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
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingMs,
        ?float $jitterMs,
        ?float $packetLossRatio,
        ?bool $healthy,
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
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => $downloadBits,
            "upload_bits" => $uploadBits,
            "ping" => $pingMs,
            "jitter" => $jitterMs,
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
