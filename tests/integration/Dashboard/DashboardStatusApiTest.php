<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function sprintf;

final class DashboardStatusApiTest extends KernelTestCase
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

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->commandBus = $container->get(MessageBusInterface::class);

        $this->db->executeStatement("DELETE FROM run_states");

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
    }

    public function testRunStatusReportsIdleWhenNoRunInFlight(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf("/dashboard/run-status?connectionId=%s", self::CONN));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("application/json", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);
        self::assertSame("idle", $payload["state"]);
        self::assertNull($payload["startedAtUnix"]);
    }

    public function testRunStatusReportsThePersistedPhase(): void
    {
        $this->seedWorld();
        $this->login();
        $this->seedRunState("running", "2026-06-07 12:00:00");

        $response = $this->get(sprintf("/dashboard/run-status?connectionId=%s", self::CONN));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertSame("running", $payload["state"]);
        self::assertIsInt($payload["startedAtUnix"]);
    }

    public function testRunStatusRejectsInvalidConnectionUuid(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get("/dashboard/run-status?connectionId=not-a-uuid");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRunStatusRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get(sprintf("/dashboard/run-status?connectionId=%s", self::CONN));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testProbesLivenessReturnsJsonWithOnlineProbe(): void
    {
        $this->seedWorld();

        $recent = (new DateTimeImmutable("now", new DateTimeZone("UTC")))
            ->modify("-10 seconds")
            ->format("Y-m-d H:i:s");
        $this->db->executeStatement(
            "UPDATE probes SET last_poll_at = :ts WHERE id = :id",
            ["ts" => $recent, "id" => self::PROBE],
        );
        $this->login();

        $response = $this->get("/dashboard/probes-liveness");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString("application/json", (string)$response->headers->get("Content-Type"));
        self::assertStringContainsString("no-cache", (string)$response->headers->get("Cache-Control"));

        $payload = $this->decode($response);
        self::assertArrayHasKey("probes", $payload);
        self::assertIsArray($payload["probes"]);
        self::assertNotEmpty($payload["probes"]);

        $probe = $payload["probes"][0];

        foreach (["probeId", "name", "isOnline", "lastPollAtUnix", "minutesSincePoll"] as $field) {
            self::assertArrayHasKey($field, $probe);
        }
        self::assertSame(self::PROBE, $probe["probeId"]);
        self::assertTrue($probe["isOnline"]);
        self::assertIsInt($probe["lastPollAtUnix"]);
    }

    public function testProbesLivenessReportsOfflineBeyondTheWindow(): void
    {
        $this->seedWorld();

        $stale = (new DateTimeImmutable("now", new DateTimeZone("UTC")))
            ->modify("-10 minutes")
            ->format("Y-m-d H:i:s");
        $this->db->executeStatement(
            "UPDATE probes SET last_poll_at = :ts WHERE id = :id",
            ["ts" => $stale, "id" => self::PROBE],
        );
        $this->login();

        $response = $this->get("/dashboard/probes-liveness");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertFalse($payload["probes"][0]["isOnline"]);
    }

    public function testProbesLivenessRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get("/dashboard/probes-liveness");

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
    }

    private function seedRunState(string $phase, string $updatedAt): void
    {
        $this->db->insert("run_states", [
            "connection_id" => self::CONN,
            "phase" => $phase,
            "updated_at" => $updatedAt,
        ]);
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
}
