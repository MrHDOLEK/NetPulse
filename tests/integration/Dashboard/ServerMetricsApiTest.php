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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class ServerMetricsApiTest extends KernelTestCase
{
    private const string CSRF_TOKEN_ID = "authenticate";
    private const string CSRF_RAW_TOKEN = "phpunit-login-token";
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";
    private const string PROBE = "11111111-1111-1111-1111-111111111111";
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";

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

    public function testReturnsServersJsonForAValidRequest(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/servers?window=30d");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("application/json", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);

        self::assertSame("30d", $payload["window"]);
        self::assertArrayHasKey("rows", $payload);
        self::assertNotEmpty($payload["rows"]);

        $row = $payload["rows"][0];

        foreach ([
            "serverId", "name", "location",
            "download", "downloadLabel", "upload", "uploadLabel",
            "ping", "pingLabel", "loss", "lossLabel",
            "tests", "healthPct", "healthLabel",
            "lastSeenUnix", "lastSeenLabel",
        ] as $key) {
            self::assertArrayHasKey($key, $row);
        }

        self::assertSame("12345", $row["serverId"]);
        self::assertStringContainsString("Mbps", $row["downloadLabel"]);
    }

    public function testRejectsUnknownWindow(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/servers?window=24h");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsMissingWindow(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/servers");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get("/dashboard/servers?window=30d");

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
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

    private function attachSession(Request $request): void
    {
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), $this->session->getId());
    }

    private function seedMeasurements(): void
    {
        $now = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->getTimestamp();

        $this->insertMeasurement("completed", $now - 3 * 3600, 920_000_000, 12.0, true);
        $this->insertMeasurement("completed", $now - 2 * 3600, 940_000_000, 11.0, true);
        $this->insertMeasurement("completed", $now - 1 * 3600, 955_000_000, 9.5, true);
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
        ?int $downloadBits,
        ?float $pingMs,
        ?bool $healthy,
    ): void {
        $completedAt = (new DateTimeImmutable("@" . $completedAtUnix))
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");

        $this->db->insert("measurements", [
            "id" => sprintf("ffffffff-0000-0000-0000-%012d", ++$this->measurementSeq),
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
            "upload_bits" => $downloadBits,
            "ping" => $pingMs,
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
