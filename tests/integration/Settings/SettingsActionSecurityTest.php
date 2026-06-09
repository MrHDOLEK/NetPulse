<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
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

final class SettingsActionSecurityTest extends KernelTestCase
{
    private const string CSRF_LOGIN_ID = "authenticate";
    private const string CSRF_LOGIN_RAW = "phpunit-login-token";
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";
    private const string SECRET = "top-secret-oidc-value";

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

    protected function tearDown(): void
    {
        $this->db->executeStatement("DELETE FROM app_settings");

        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->clear();

        parent::tearDown();
    }

    public function testUnauthenticatedGeneralSaveRedirectsToLoginAndDoesNothing(): void
    {
        $response = $this->postJson("/settings/general", ["siteName" => "Hacked", "timezone" => ""], null);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
        self::assertNull($this->storedValue(SettingKey::SiteName), "no setting may be written while unauthenticated");
    }

    public function testUnauthenticatedSsoSaveRedirectsToLogin(): void
    {
        $response = $this->postJson("/settings/sso", ["clientId" => "x"], null);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testGeneralSaveWithoutCsrfIsForbidden(): void
    {
        $this->login();

        $response = $this->postJson("/settings/general", ["siteName" => "Acme", "timezone" => ""], null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertNull($this->storedValue(SettingKey::SiteName), "no setting may be written without a CSRF token");
    }

    public function testGeneralSaveWithBadCsrfIsForbidden(): void
    {
        $this->login();
        $this->seedCsrf("settings-general");

        $response = $this->postJson("/settings/general", ["siteName" => "Acme", "timezone" => ""], "not-the-token");

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertNull($this->storedValue(SettingKey::SiteName));
    }

    public function testGeneralSaveWithValidCsrfPersists(): void
    {
        $this->login();
        $token = $this->seedCsrf("settings-general");

        $response = $this->postJson(
            "/settings/general",
            ["siteName" => "Acme Pulse", "timezone" => "Europe/Warsaw"],
            $token,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame("Acme Pulse", $this->reader()->getString(SettingKey::SiteName));
        self::assertSame("Europe/Warsaw", $this->reader()->getString(SettingKey::Timezone));
    }

    public function testSsoSaveWithBadCsrfIsForbidden(): void
    {
        $this->login();
        $this->seedCsrf("settings-sso");

        $response = $this->postJson("/settings/sso", ["clientId" => "x"], "wrong");

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testSsoSaveStoresSecretEncryptedAndPageNeverEchoesIt(): void
    {
        $this->login();
        $token = $this->seedCsrf("settings-sso");

        $response = $this->postJson("/settings/sso", [
            "enabled" => true,
            "name" => "Company SSO",
            "clientId" => "cid",
            "clientSecret" => self::SECRET,
            "authorizationUrl" => "https://idp.example/authorize",
            "tokenUrl" => "https://idp.example/token",
            "userInfoUrl" => "https://idp.example/userinfo",
            "redirectUrl" => "https://app.example/login/oidc/callback",
            "scopes" => "openid email profile",
        ], $token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($this->decode($response)["secretIsSet"] ?? false);

        self::assertSame(self::SECRET, $this->reader()->get(SettingKey::OidcClientSecret));

        $stored = $this->storedValue(SettingKey::OidcClientSecret);
        self::assertIsString($stored);
        self::assertNotSame(self::SECRET, $stored);
        self::assertStringNotContainsString(self::SECRET, $stored);

        $page = $this->get("/settings/sso");
        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringNotContainsString(self::SECRET, $html, "the write-only secret must never be echoed back");
        self::assertStringContainsString("•••• set", $html, "the page shows a secret-is-set indicator");
        self::assertStringContainsString("cid", $html, "non-secret fields ARE rendered");
    }

    public function testSsoBlankSecretKeepsExistingSecret(): void
    {
        $this->login();

        $token = $this->seedCsrf("settings-sso");
        $this->postJson("/settings/sso", ["clientId" => "cid", "clientSecret" => self::SECRET], $token);

        $token = $this->seedCsrf("settings-sso");
        $response = $this->postJson("/settings/sso", ["clientId" => "cid2"], $token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(self::SECRET, $this->reader()->get(SettingKey::OidcClientSecret));
        self::assertSame("cid2", $this->reader()->getString(SettingKey::OidcClientId));
    }

    private function reader(): SettingsReader
    {
        $reader = self::getContainer()->get("test." . SettingsReader::class);
        self::assertInstanceOf(SettingsReader::class, $reader);

        return $reader;
    }

    private function storedValue(SettingKey $key): ?string
    {
        $value = $this->db->fetchOne(
            "SELECT value FROM app_settings WHERE setting_key = ?",
            [$key->value],
        );

        return $value === false ? null : (string)$value;
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
}
