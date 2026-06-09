<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Application\Oidc\OidcException;
use App\Auth\Application\Oidc\OidcIdentity;
use App\Auth\Application\Oidc\OidcProvider;
use App\Auth\Infrastructure\Oidc\OidcConfig;
use App\Auth\Infrastructure\Symfony\Security\OidcAuthenticator;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;

final class OidcLoginTest extends KernelTestCase
{
    private const string ADMIN_EMAIL = "admin@example.com";
    private const string ADMIN_PASSWORD = "correct-horse-battery";
    private const string STUB_AUTH_URL = "https://idp.example/authorize?stub=1";

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
    }

    public function testLoginPageShowsTheSsoButtonWhenEnabled(): void
    {
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub", self::ADMIN_EMAIL, true, "Admin"));

        $response = $this->get("/login");

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString("Continue with", $html);
        self::assertStringContainsString("Company SSO", $html);
        self::assertStringContainsString("/login/oidc/start", $html);
    }

    public function testStartRedirectsToIdpAndSeedsSessionMaterial(): void
    {
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub", self::ADMIN_EMAIL, true, "Admin"));

        $response = $this->get("/login/oidc/start");

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(self::STUB_AUTH_URL, $response->headers->get("Location"));

        self::assertTrue($this->session->has(OidcAuthenticator::SESSION_STATE));
        self::assertTrue($this->session->has(OidcAuthenticator::SESSION_VERIFIER));
        self::assertTrue($this->session->has(OidcAuthenticator::SESSION_NONCE));

        $verifier = (string)$this->session->get(OidcAuthenticator::SESSION_VERIFIER);
        self::assertGreaterThanOrEqual(43, strlen($verifier));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier);
    }

    public function testCallbackWithVerifiedExistingEmailAuthenticatesAndRedirectsHome(): void
    {
        $this->seedAdmin();
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub-1", self::ADMIN_EMAIL, true, "Admin"));

        $this->get("/login/oidc/start");
        $state = (string)$this->session->get(OidcAuthenticator::SESSION_STATE);

        $response = $this->get("/login/oidc/callback?code=auth-code&state=" . $state);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame("/", $response->headers->get("Location"));

        $token = $this->session->get("_security_main");
        self::assertNotNull($token, "expected an authenticated security token in the session");
        self::assertStringContainsString(self::ADMIN_EMAIL, (string)$token);
    }

    public function testCallbackWithUnknownEmailDeniesAndCreatesNoUser(): void
    {
        $this->seedAdmin();
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub-9", "stranger@example.com", true, "Stranger"));

        $before = $this->userCount();

        $this->get("/login/oidc/start");
        $state = (string)$this->session->get(OidcAuthenticator::SESSION_STATE);

        $response = $this->get("/login/oidc/callback?code=auth-code&state=" . $state);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));

        self::assertNull($this->session->get("_security_main"));

        self::assertSame($before, $this->userCount());
        self::assertSame(1, $this->userCount());
    }

    public function testCallbackWithUnverifiedEmailIsDenied(): void
    {
        $this->seedAdmin();
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub-1", self::ADMIN_EMAIL, false, "Admin"));

        $this->get("/login/oidc/start");
        $state = (string)$this->session->get(OidcAuthenticator::SESSION_STATE);

        $response = $this->get("/login/oidc/callback?code=auth-code&state=" . $state);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
        self::assertNull($this->session->get("_security_main"));
    }

    public function testCallbackWithProviderFailureIsDenied(): void
    {
        $this->seedAdmin();
        $this->enableOidc();
        $this->stubProvider(null);

        $this->get("/login/oidc/start");
        $state = (string)$this->session->get(OidcAuthenticator::SESSION_STATE);

        $response = $this->get("/login/oidc/callback?code=auth-code&state=" . $state);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
        self::assertNull($this->session->get("_security_main"));
    }

    public function testCallbackWithForgedStateIsDenied(): void
    {
        $this->seedAdmin();
        $this->enableOidc();
        $this->stubProvider(new OidcIdentity("sub-1", self::ADMIN_EMAIL, true, "Admin"));

        $this->get("/login/oidc/start");

        $response = $this->get("/login/oidc/callback?code=auth-code&state=forged-" . bin2hex(random_bytes(4)));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString("/login", (string)$response->headers->get("Location"));
        self::assertNull($this->session->get("_security_main"));
    }

    public function testDisabledByDefaultHidesButtonAndStartReturns404(): void
    {
        $this->seedAdmin();

        $login = $this->get("/login");
        self::assertSame(Response::HTTP_OK, $login->getStatusCode());
        $html = (string)$login->getContent();
        self::assertStringNotContainsString("Continue with", $html);
        self::assertStringNotContainsString("/login/oidc/start", $html);

        $start = $this->get("/login/oidc/start");
        self::assertSame(Response::HTTP_NOT_FOUND, $start->getStatusCode());
    }

    private function enableOidc(): void
    {
        self::getContainer()->set(OidcConfig::class, new OidcConfig(
            "client-id",
            "client-secret",
            "https://idp.example/authorize",
            "https://idp.example/token",
            "https://idp.example/userinfo",
            "https://app.example/login/oidc/callback",
            "openid email profile",
            "Company SSO",
        ));
    }

    private function stubProvider(?OidcIdentity $identity): void
    {
        self::getContainer()->set(OidcProvider::class, new class(self::STUB_AUTH_URL, $identity) implements OidcProvider {
            public function __construct(
                private readonly string $authUrl,
                private readonly ?OidcIdentity $identity,
            ) {}

            public function authorizationUrl(string $state, string $codeVerifier, string $nonce): string
            {
                return $this->authUrl;
            }

            public function exchange(string $code, string $codeVerifier, string $expectedNonce): OidcIdentity
            {
                if ($this->identity === null) {
                    throw new OidcException("stubbed exchange failure");
                }

                return $this->identity;
            }
        });
    }

    private function seedAdmin(): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::ADMIN_EMAIL, self::ADMIN_PASSWORD));
    }

    private function userCount(): int
    {
        return (int)$this->db->fetchOne("SELECT COUNT(*) FROM users");
    }

    private function get(string $path): Response
    {
        $request = Request::create($path, "GET");
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), $this->session->getId());

        return self::getContainer()->get("kernel")->handle($request);
    }
}
