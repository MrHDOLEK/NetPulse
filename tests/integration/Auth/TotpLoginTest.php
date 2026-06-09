<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\TotpSecret;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class TotpLoginTest extends KernelTestCase
{
    private const string LOGIN_CSRF_ID = "authenticate";
    private const string LOGIN_CSRF_RAW = "phpunit-login-token";
    private const string TWOFA_CSRF_ID = "two_factor";
    private const string TWOFA_CSRF_RAW = "phpunit-2fa-token";
    private const string EMAIL = "admin@example.com";
    private const string PASSWORD = "correct-horse-battery";    
    private const string SECRET = "JBSWY3DPEHPK3PXP";

    private DbalConnection $db;
    private MessageBusInterface $commandBus;
    private UserRepository $users;
    private EntityManagerInterface $em;
    private PasswordHasherFactoryInterface $hasherFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get("doctrine.dbal.default_connection");
        $this->commandBus = $container->get(MessageBusInterface::class);
        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasherFactory = $container->get(PasswordHasherFactoryInterface::class);

        $users = $container->get("test." . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;
    }

    public function testPasswordLeavesUserHalfAuthenticatedAndDashboardUnreachable(): void
    {
        $this->seedAdminWithTotp([]);
        $session = $this->newSession();

        $loginResponse = $this->login($session);

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        $dashboard = $this->request($session, "/", "GET");
        self::assertSame(Response::HTTP_FOUND, $dashboard->getStatusCode());
        self::assertStringContainsString("/2fa", (string)$dashboard->headers->get("Location"));
    }

    public function testValidTotpCodeCompletesAuthentication(): void
    {
        $this->seedAdminWithTotp([]);
        $session = $this->newSession();
        $this->login($session);

        $check = $this->submitAuthCode($session, TOTP::createFromSecret(self::SECRET)->now());
        self::assertSame(Response::HTTP_FOUND, $check->getStatusCode());

        $dashboard = $this->request($session, "/", "GET");
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
    }

    public function testRecoveryCodeAuthenticatesAndIsSingleUse(): void
    {
        $recoveryCode = "abc12-def34";
        $this->seedAdminWithTotp([$recoveryCode]);

        $session = $this->newSession();
        $this->login($session);

        $check = $this->submitAuthCode($session, $recoveryCode);
        self::assertSame(Response::HTTP_FOUND, $check->getStatusCode());

        $dashboard = $this->request($session, "/", "GET");
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());

        $user = $this->reloadUser();
        $hasher = $this->hasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);

        foreach ($user->recoveryCodes() as $hash) {
            self::assertFalse($hasher->verify($hash, $recoveryCode), "recovery code was not consumed");
        }

        $session2 = $this->newSession();
        $this->login($session2);
        $reuse = $this->submitAuthCode($session2, $recoveryCode);
        self::assertSame(Response::HTTP_FOUND, $reuse->getStatusCode());

        $dashboard2 = $this->request($session2, "/", "GET");
        self::assertSame(Response::HTTP_FOUND, $dashboard2->getStatusCode());
        self::assertStringContainsString("/2fa", (string)$dashboard2->headers->get("Location"));
    }

    public function testWrongCodeKeepsUserHalfAuthenticated(): void
    {
        $this->seedAdminWithTotp([]);
        $session = $this->newSession();
        $this->login($session);

        $this->submitAuthCode($session, "000000");

        $dashboard = $this->request($session, "/", "GET");
        self::assertSame(Response::HTTP_FOUND, $dashboard->getStatusCode());
        self::assertStringContainsString("/2fa", (string)$dashboard->headers->get("Location"));
    }

    public function testUserWithoutTotpLogsInWithSinglePasswordFactor(): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::EMAIL, self::PASSWORD));

        $session = $this->newSession();
        $loginResponse = $this->login($session);

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());
        self::assertStringNotContainsString("/2fa", (string)$loginResponse->headers->get("Location"));

        $dashboard = $this->request($session, "/", "GET");
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
    }

    public function testStoredSecretIsEncryptedAtRest(): void
    {
        $this->seedAdminWithTotp([]);

        $stored = $this->db->fetchOne("SELECT totp_secret FROM users WHERE email = ?", [self::EMAIL]);
        self::assertIsString($stored);
        self::assertNotSame(self::SECRET, $stored);
        self::assertStringNotContainsString(self::SECRET, $stored);
    }

    /**
     * @param list<string> $plainRecoveryCodes
     */
    private function seedAdminWithTotp(array $plainRecoveryCodes): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::EMAIL, self::PASSWORD));

        $user = $this->users->byEmail(new Email(self::EMAIL));
        self::assertNotNull($user);

        $hasher = $this->hasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
        $hashed = array_map(static fn(string $code): string => $hasher->hash($code), $plainRecoveryCodes);

        $user->enableTotp(new TotpSecret(self::SECRET), array_values($hashed));
        $this->users->save($user);
        $this->em->clear();
    }

    private function reloadUser(): User
    {
        $this->em->clear();
        $user = $this->users->byEmail(new Email(self::EMAIL));
        self::assertNotNull($user);

        return $user;
    }

    private function newSession(): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        return $session;
    }

    private function login(Session $session): Response
    {
        $session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::LOGIN_CSRF_ID,
            self::LOGIN_CSRF_RAW,
        );

        return $this->request($session, "/login", "POST", [
            "_username" => self::EMAIL,
            "_password" => self::PASSWORD,
            "_csrf_token" => self::LOGIN_CSRF_RAW,
        ]);
    }

    private function submitAuthCode(Session $session, string $code): Response
    {
        $session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::TWOFA_CSRF_ID,
            self::TWOFA_CSRF_RAW,
        );

        return $this->request($session, "/2fa_check", "POST", [
            "_auth_code" => $code,
            "_csrf_token" => self::TWOFA_CSRF_RAW,
        ]);
    }

    /**
     * @param array<string, string> $body
     */
    private function request(Session $session, string $path, string $method, array $body = []): Response
    {
        $request = Request::create($path, $method, $body);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());

        return self::getContainer()->get("kernel")->handle($request);
    }
}
