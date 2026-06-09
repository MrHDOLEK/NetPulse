<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function is_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ManagementActionSecurityTest extends KernelTestCase
{
    private const string CSRF_LOGIN_ID = "authenticate";
    private const string CSRF_LOGIN_RAW = "phpunit-login-token";
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";
    private const string PROBE = "11111111-1111-1111-1111-111111111111";

    private DbalConnection $db;
    private MessageBusInterface $commandBus;
    private Session $session;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->commandBus = $container->get(MessageBusInterface::class);

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();

        $this->commandBus->dispatch(new CreateAdminCommand(self::ADMIN_EMAIL, self::ADMIN_PASSWORD));
    }

    public function testUnauthenticatedProbeCreateRedirectsToLoginAndDoesNothing(): void
    {
        $before = $this->probeCount();

        $response = $this->postJson("/settings/probes", ["name" => "edge"], null);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
        self::assertSame($before, $this->probeCount(), "no probe may be created while unauthenticated");
    }

    public function testUnauthenticatedConnectionCreateRedirectsToLogin(): void
    {
        $response = $this->postJson("/settings/connections", $this->connectionBody(), null);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testProbeCreateWithoutCsrfIsForbidden(): void
    {
        $this->login();
        $before = $this->probeCount();

        $response = $this->postJson("/settings/probes", ["name" => "edge"], null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame($before, $this->probeCount(), "no probe may be created without a CSRF token");
    }

    public function testProbeCreateWithValidCsrfSucceedsAndReturnsTokenOnce(): void
    {
        $this->login();
        $token = $this->seedCsrf("probe-create");
        $before = $this->probeCount();

        $response = $this->postJson("/settings/probes", ["name" => "edge", "labels" => ""], $token);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertArrayHasKey("id", $payload);
        self::assertArrayHasKey("token", $payload);
        self::assertIsString($payload["token"]);
        self::assertNotSame("", $payload["token"]);
        self::assertSame($before + 1, $this->probeCount());
    }

    public function testRotateProbeTokenReturnsFreshPlaintextNeverExposedByTheList(): void
    {
        $this->insertProbe();
        $this->login();
        $token = $this->seedCsrf("probe-rotate");

        $response = $this->postJson("/settings/probes/" . self::PROBE . "/rotate-token", [], $token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $plaintext = $this->decode($response)["token"] ?? null;
        self::assertIsString($plaintext);
        self::assertNotSame("", $plaintext);

        $storedHash = (string)$this->db->fetchOne(
            "SELECT token_hash FROM probes WHERE id = :id",
            ["id" => self::PROBE],
        );
        self::assertNotSame("x", $storedHash);
        self::assertNotSame($plaintext, $storedHash, "the plaintext token is never persisted");

        $list = $this->get("/settings/probes");
        self::assertSame(Response::HTTP_OK, $list->getStatusCode());
        self::assertStringNotContainsString($plaintext, (string)$list->getContent());
    }

    public function testConnectionListPageRendersForAdmin(): void
    {
        $this->insertProbe();
        $this->login();
        $token = $this->seedCsrf("connection-create");
        $this->postJson("/settings/connections", $this->connectionBody(), $token);

        $response = $this->get("/settings/connections");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString("Fibre WAN", $html, "the created connection renders in the list");
        self::assertStringContainsString("csrf-connection-create", $html, "the connections management view rendered");
    }

    public function testConnectionCreateWithValidCsrfSucceeds(): void
    {
        $this->insertProbe();
        $this->login();
        $token = $this->seedCsrf("connection-create");
        $before = $this->connectionCount();

        $response = $this->postJson("/settings/connections", $this->connectionBody(), $token);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertArrayHasKey("id", $this->decode($response));
        self::assertSame($before + 1, $this->connectionCount());
    }

    public function testConnectionCreateWithBadCsrfIsForbidden(): void
    {
        $this->insertProbe();
        $this->login();
        $this->seedCsrf("connection-create");
        $before = $this->connectionCount();

        $response = $this->postJson("/settings/connections", $this->connectionBody(), "not-the-token");

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame($before, $this->connectionCount(), "no connection may be created with a bad CSRF token");
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionBody(): array
    {
        return [
            "probeId" => self::PROBE,
            "name" => "Fibre WAN",
            "isp" => "Acme",
            "color" => "primary",
            "downloadMbps" => 300,
            "uploadMbps" => 50,
            "labels" => "",
            "serverPool" => "",
            "scheduleMode" => "even",
            "cron" => "",
            "testsPerDay" => 24,
            "jitter" => 120,
        ];
    }

    private function login(): void
    {
        $this->session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::CSRF_LOGIN_ID,
            self::CSRF_LOGIN_RAW,
        );

        $request = Request::create("/login", "POST", [
            "_username" => self::ADMIN_EMAIL,
            "_password" => self::ADMIN_PASSWORD,
            "_csrf_token" => self::CSRF_LOGIN_RAW,
        ]);
        $this->attachSession($request);

        self::getContainer()->get("kernel")->handle($request);
    }

    private function seedCsrf(string $id): string
    {
        $raw = "phpunit-" . $id . "-token";
        $this->session->set(SessionTokenStorage::SESSION_NAMESPACE . "/" . $id, $raw);

        return $raw;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $path, array $body, ?string $csrfToken): Response
    {
        $request = Request::create(
            $path,
            "POST",
            [],
            [],
            [],
            ["CONTENT_TYPE" => "application/json"],
            json_encode($body, JSON_THROW_ON_ERROR),
        );

        if ($csrfToken !== null) {
            $request->headers->set("X-CSRF-Token", $csrfToken);
        }
        $this->attachSession($request);

        return self::getContainer()->get("kernel")->handle($request);
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

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
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

    private function probeCount(): int
    {
        return (int)$this->db->fetchOne("SELECT COUNT(*) FROM probes");
    }

    private function connectionCount(): int
    {
        return (int)$this->db->fetchOne("SELECT COUNT(*) FROM connections");
    }
}
