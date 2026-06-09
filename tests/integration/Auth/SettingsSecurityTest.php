<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class SettingsSecurityTest extends KernelTestCase
{
    private const string LOGIN_CSRF_ID = "authenticate";
    private const string LOGIN_CSRF_RAW = "phpunit-login-token";
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";

    private DbalConnection $db;
    private MessageBusInterface $commandBus;
    private UserRepository $users;
    private EntityManagerInterface $em;
    private Session $session;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->commandBus = $container->get(MessageBusInterface::class);
        $this->em = $container->get(EntityManagerInterface::class);

        $users = $container->get("test." . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
    }

    public function testStatusPageShowsOffWhenTotpDisabled(): void
    {
        $this->seedAdmin();
        $this->login();

        $response = $this->get("/settings/security");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString("Set up two-factor authentication", $html);
        self::assertStringContainsString("Off", $html);
    }

    public function testRequiresAuthentication(): void
    {
        $this->seedAdmin();

        $response = $this->get("/settings/security");

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
    }

    public function testBeginStashesSecretInSessionAndRendersEnrolPage(): void
    {
        $this->seedAdmin();
        $this->login();

        $response = $this->post("/settings/security/2fa/begin", [
            "_csrf_token" => $this->csrf("twofa_begin"),
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);
        self::assertNotSame("", $secret);

        $html = (string)$response->getContent();

        self::assertStringContainsString("data:image/png;base64,", $html);
        self::assertStringContainsString($secret, $html);
        self::assertStringContainsString('name="code"', $html);

        self::assertFalse($this->reloadUser()->hasTotp());
    }

    public function testConfirmWithValidCodeEnablesTotpAndStoresHashedRecoveryCodes(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);

        $code = TOTP::createFromSecret($secret)->now();

        $response = $this->post("/settings/security/2fa/confirm", [
            "_csrf_token" => $this->csrf("twofa_confirm"),
            "code" => $code,
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $user = $this->reloadUser();
        self::assertTrue($user->hasTotp());

        $recoveryCodes = $user->recoveryCodes();
        self::assertCount(8, $recoveryCodes);

        $html = (string)$response->getContent();
        $shown = $this->extractShownCodes($html);
        self::assertCount(8, $shown, "expected 8 plaintext recovery codes on the page");

        foreach ($shown as $plain) {
            self::assertNotContains($plain, $recoveryCodes, "plaintext code must NOT be stored verbatim");
        }

        $stored = (string)$this->db->fetchOne(
            "SELECT recovery_codes FROM users WHERE email = ?",
            [self::ADMIN_EMAIL],
        );

        foreach ($shown as $plain) {
            self::assertStringNotContainsString($plain, $stored, "plaintext code leaked into the DB column");
        }

        $storedSecret = $this->db->fetchOne("SELECT totp_secret FROM users WHERE email = ?", [self::ADMIN_EMAIL]);
        self::assertIsString($storedSecret);
        self::assertNotSame($secret, $storedSecret);
        self::assertStringNotContainsString($secret, $storedSecret);
    }

    public function testConfirmWithWrongCodeDoesNotEnableTotp(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);

        $response = $this->post("/settings/security/2fa/confirm", [
            "_csrf_token" => $this->csrf("twofa_confirm"),
            "code" => "000000",
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertFalse($this->reloadUser()->hasTotp());
    }

    public function testConfirmRejectedWithoutCsrfToken(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);

        $code = TOTP::createFromSecret($secret)->now();

        $response = $this->post("/settings/security/2fa/confirm", ["code" => $code]);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/settings/security", (string)$response->headers->get("Location"));
        self::assertFalse($this->reloadUser()->hasTotp());
    }

    public function testDisableTurnsTotpOff(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);
        $this->post("/settings/security/2fa/confirm", [
            "_csrf_token" => $this->csrf("twofa_confirm"),
            "code" => TOTP::createFromSecret($secret)->now(),
        ]);
        self::assertTrue($this->reloadUser()->hasTotp());

        $response = $this->post("/settings/security/2fa/disable", [
            "_csrf_token" => $this->csrf("twofa_disable"),
            "confirm" => "DISABLE",
        ]);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $user = $this->reloadUser();
        self::assertFalse($user->hasTotp());
        self::assertSame([], $user->recoveryCodes());
    }

    public function testDisableRequiresTypedConfirmation(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);
        $this->post("/settings/security/2fa/confirm", [
            "_csrf_token" => $this->csrf("twofa_confirm"),
            "code" => TOTP::createFromSecret($secret)->now(),
        ]);
        self::assertTrue($this->reloadUser()->hasTotp());

        $response = $this->post("/settings/security/2fa/disable", [
            "_csrf_token" => $this->csrf("twofa_disable"),
        ]);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertTrue($this->reloadUser()->hasTotp());
    }

    public function testRegenerateReplacesRecoveryCodes(): void
    {
        $this->seedAdmin();
        $this->login();

        $this->post("/settings/security/2fa/begin", ["_csrf_token" => $this->csrf("twofa_begin")]);
        $secret = $this->session->get("twofa_setup_secret");
        self::assertIsString($secret);
        $this->post("/settings/security/2fa/confirm", [
            "_csrf_token" => $this->csrf("twofa_confirm"),
            "code" => TOTP::createFromSecret($secret)->now(),
        ]);

        $before = $this->reloadUser()->recoveryCodes();
        self::assertCount(8, $before);

        $response = $this->post("/settings/security/2fa/recovery/regenerate", [
            "_csrf_token" => $this->csrf("twofa_regenerate"),
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $after = $this->reloadUser()->recoveryCodes();
        self::assertCount(8, $after);

        self::assertNotSame($before, $after);

        $shown = $this->extractShownCodes((string)$response->getContent());
        self::assertCount(8, $shown);

        foreach ($shown as $plain) {
            self::assertNotContains($plain, $after);
        }
    }

    /**
     * @return list<string>
     */
    private function extractShownCodes(string $html): array
    {
        preg_match_all("/\b[0-9a-f]{5}-[0-9a-f]{5}\b/", $html, $matches);

        return array_values(array_unique($matches[0]));
    }

    private function seedAdmin(): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::ADMIN_EMAIL, self::ADMIN_PASSWORD));
    }

    private function reloadUser(): User
    {
        $this->em->clear();
        $user = $this->users->byEmail(new Email(self::ADMIN_EMAIL));
        self::assertNotNull($user);

        return $user;
    }

    private function login(): void
    {
        $this->session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::LOGIN_CSRF_ID,
            self::LOGIN_CSRF_RAW,
        );

        $request = Request::create("/login", "POST", [
            "_username" => self::ADMIN_EMAIL,
            "_password" => self::ADMIN_PASSWORD,
            "_csrf_token" => self::LOGIN_CSRF_RAW,
        ]);
        $this->attachSession($request);

        self::getContainer()->get("kernel")->handle($request);
    }

    private function csrf(string $tokenId): string
    {
        $raw = "phpunit-" . $tokenId;
        $this->session->set(SessionTokenStorage::SESSION_NAMESPACE . "/" . $tokenId, $raw);

        return $raw;
    }

    /**
     * @param array<string, string> $body
     */
    private function post(string $path, array $body): Response
    {
        $request = Request::create($path, "POST", $body);
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
}
