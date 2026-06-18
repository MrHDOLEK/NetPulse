<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\Application\Oidc\OidcException;
use App\Auth\Application\Oidc\OidcIdentity;
use App\Auth\Application\Oidc\OidcProvider;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Infrastructure\Symfony\Security\OidcAuthenticator;
use App\Auth\Infrastructure\Symfony\Security\UserProvider;
use App\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class OidcAuthenticatorTest extends TestCase
{
    private const string ADMIN_EMAIL = 'admin@example.com';
    private const string STATE = 'the-state-value';
    private const string VERIFIER = 'verifier-0123456789abcdef';
    private const string NONCE = 'the-nonce-value';

    private InMemoryUserRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryUserRepository();
    }

    public function testVerifiedEmailWithExistingUserYieldsPassportForThatEmail(): void
    {
        $this->seedAdmin();
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-1', self::ADMIN_EMAIL, true, 'Admin'),
        ));

        $passport = $authenticator->authenticate($this->callbackRequest(self::STATE, self::STATE));

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame(self::ADMIN_EMAIL, $badge->getUserIdentifier());

        self::assertSame(self::ADMIN_EMAIL, $badge->getUser()->getUserIdentifier());
        self::assertCount(1, $this->repository->all());
    }

    public function testUnverifiedEmailIsDenied(): void
    {
        $this->seedAdmin();
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-1', self::ADMIN_EMAIL, false, 'Admin'),
        ));

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Your SSO email is not verified');

        $authenticator->authenticate($this->callbackRequest(self::STATE, self::STATE));
    }

    public function testProviderExceptionIsDenied(): void
    {
        $this->seedAdmin();
        $authenticator = $this->authenticator(StubOidcProvider::throwing());

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('SSO sign-in failed');

        $authenticator->authenticate($this->callbackRequest(self::STATE, self::STATE));
    }

    public function testMismatchedStateIsDeniedBeforeAnyExchange(): void
    {
        $this->seedAdmin();
        $provider = new StubOidcProvider(new OidcIdentity('sub-1', self::ADMIN_EMAIL, true, 'Admin'));
        $authenticator = $this->authenticator($provider);

        try {
            $authenticator->authenticate($this->callbackRequest('session-state', 'forged-state'));
            self::fail('Expected a state mismatch to be denied.');
        } catch (CustomUserMessageAuthenticationException $exception) {
            self::assertSame('Invalid SSO state', $exception->getMessageKey());
        }

        self::assertFalse($provider->exchangeCalled);
    }

    public function testMissingSessionStateIsDenied(): void
    {
        $this->seedAdmin();
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-1', self::ADMIN_EMAIL, true, 'Admin'),
        ));

        $request = $this->callbackRequest(null, self::STATE);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid SSO state');

        $authenticator->authenticate($request);
    }

    public function testUnknownEmailIsDeniedAndCreatesNoUser(): void
    {
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-9', 'stranger@example.com', true, 'Stranger'),
        ));

        $passport = $authenticator->authenticate($this->callbackRequest(self::STATE, self::STATE));
        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);

        try {
            $badge->getUser();
            self::fail('Expected an unknown email to be denied.');
        } catch (CustomUserMessageAuthenticationException $exception) {
            self::assertSame('No NetPulse account for this email', $exception->getMessageKey());
        }

        self::assertCount(0, $this->repository->all());
    }

    public function testSessionMaterialIsClearedAfterUse(): void
    {
        $this->seedAdmin();
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-1', self::ADMIN_EMAIL, true, 'Admin'),
        ));

        $request = $this->callbackRequest(self::STATE, self::STATE);
        $authenticator->authenticate($request);

        $session = $request->getSession();
        self::assertFalse($session->has(OidcAuthenticator::SESSION_STATE));
        self::assertFalse($session->has(OidcAuthenticator::SESSION_VERIFIER));
        self::assertFalse($session->has(OidcAuthenticator::SESSION_NONCE));
    }

    public function testSupportsOnlyTheCallbackRoute(): void
    {
        $authenticator = $this->authenticator(new StubOidcProvider(
            new OidcIdentity('sub-1', self::ADMIN_EMAIL, true, 'Admin'),
        ));

        $callback = Request::create('/login/oidc/callback');
        $callback->attributes->set('_route', OidcAuthenticator::CALLBACK_ROUTE);
        self::assertTrue($authenticator->supports($callback));

        $login = Request::create('/login');
        $login->attributes->set('_route', 'login');
        self::assertFalse($authenticator->supports($login));
    }

    private function authenticator(OidcProvider $provider): OidcAuthenticator
    {
        return new OidcAuthenticator($provider, new UserProvider($this->repository));
    }

    private function seedAdmin(): void
    {
        $this->repository->save(User::register(
            new UserId('11111111-1111-1111-1111-111111111111'),
            new Email(self::ADMIN_EMAIL),
            HashedPassword::fromHash('dummy-hash-not-used-by-sso'),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            new DateTimeImmutable('2026-06-01 10:00:00'),
        ));
    }

    private function callbackRequest(?string $sessionState, string $queryState): Request
    {
        $request = Request::create('/login/oidc/callback', 'GET', [
            'code' => 'auth-code-123',
            'state' => $queryState,
        ]);
        $request->attributes->set('_route', OidcAuthenticator::CALLBACK_ROUTE);

        $session = new Session(new MockArraySessionStorage());
        $session->start();

        if ($sessionState !== null) {
            $session->set(OidcAuthenticator::SESSION_STATE, $sessionState);
            $session->set(OidcAuthenticator::SESSION_VERIFIER, self::VERIFIER);
            $session->set(OidcAuthenticator::SESSION_NONCE, self::NONCE);
        }

        $request->setSession($session);

        return $request;
    }
}

final class StubOidcProvider implements OidcProvider
{
    public bool $exchangeCalled = false;

    public function __construct(
        private readonly ?OidcIdentity $identity,
    ) {}

    public static function throwing(): self
    {
        return new self(null);
    }

    public function authorizationUrl(string $state, string $codeVerifier, string $nonce): string
    {
        return 'https://idp.example/authorize?state=' . $state;
    }

    public function exchange(string $code, string $codeVerifier, string $expectedNonce): OidcIdentity
    {
        $this->exchangeCalled = true;

        if ($this->identity === null) {
            throw new OidcException('stubbed exchange failure');
        }

        return $this->identity;
    }
}
