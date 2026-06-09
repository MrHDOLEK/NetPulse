<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\Security;

use App\Auth\Application\Oidc\OidcException;
use App\Auth\Application\Oidc\OidcProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

use function hash_equals;
use function is_string;

final class OidcAuthenticator extends AbstractAuthenticator
{
    public const string SESSION_STATE = "oidc_state";
    public const string SESSION_VERIFIER = "oidc_verifier";
    public const string SESSION_NONCE = "oidc_nonce";
    public const string CALLBACK_ROUTE = "oidc_callback";

    public function __construct(
        private readonly OidcProvider $provider,
        private readonly UserProvider $users,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->attributes->get("_route") === self::CALLBACK_ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();

        $expectedState = $session->get(self::SESSION_STATE);
        $codeVerifier = $session->get(self::SESSION_VERIFIER);
        $expectedNonce = $session->get(self::SESSION_NONCE);

        $session->remove(self::SESSION_STATE);
        $session->remove(self::SESSION_VERIFIER);
        $session->remove(self::SESSION_NONCE);

        $queryState = $request->query->get("state");

        if (!is_string($expectedState) || $expectedState === "" || !is_string($queryState) || !hash_equals($expectedState, $queryState)) {
            throw new CustomUserMessageAuthenticationException("Invalid SSO state");
        }

        $code = $request->query->get("code");

        if (!is_string($code) || $code === "") {
            throw new CustomUserMessageAuthenticationException("SSO sign-in was not completed");
        }

        try {
            $identity = $this->provider->exchange(
                $code,
                is_string($codeVerifier) ? $codeVerifier : "",
                is_string($expectedNonce) ? $expectedNonce : "",
            );
        } catch (OidcException) {
            throw new CustomUserMessageAuthenticationException("SSO sign-in failed");
        }

        if (!$identity->emailVerified) {
            throw new CustomUserMessageAuthenticationException("Your SSO email is not verified");
        }

        return new SelfValidatingPassport(
            new UserBadge($identity->email, function (string $email): SecurityUser {
                try {
                    return $this->users->loadUserByIdentifier($email);
                } catch (UserNotFoundException $exception) {
                    throw new CustomUserMessageAuthenticationException(
                        "No NetPulse account for this email",
                        previous: $exception,
                    );
                }
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse("/");
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse("/login");
    }
}
