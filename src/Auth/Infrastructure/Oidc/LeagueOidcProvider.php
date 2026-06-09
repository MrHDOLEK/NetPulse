<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Oidc;

use App\Auth\Application\Oidc\OidcException;
use App\Auth\Application\Oidc\OidcIdentity;
use App\Auth\Application\Oidc\OidcProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;
use function json_decode;
use function rtrim;
use function strtr;
use function trim;

use const JSON_THROW_ON_ERROR;

#[AsAlias(OidcProvider::class)]
final class LeagueOidcProvider implements OidcProvider
{
    private ?GenericProvider $provider = null;

    public function __construct(
        private readonly OidcConfig $config,
    ) {}

    public function authorizationUrl(string $state, string $codeVerifier, string $nonce): string
    {
        return $this->provider()->getAuthorizationUrl([
            "state" => $state,
            "scope" => $this->config->scopes,
            "nonce" => $nonce,
            "code_challenge" => $this->s256($codeVerifier),
            "code_challenge_method" => "S256",
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $expectedNonce): OidcIdentity
    {
        try {
            $token = $this->provider()->getAccessToken("authorization_code", [
                "code" => $code,
                "code_verifier" => $codeVerifier,
            ]);

            if (!$token instanceof AccessToken) {
                throw new OidcException("OIDC token exchange returned an unexpected token type.");
            }

            /** @var array<string, mixed> $tokenValues */
            $tokenValues = $token->getValues();
            $this->assertNonce($tokenValues, $expectedNonce);

            /** @var array<string, mixed> $owner */
            $owner = $this->provider()->getResourceOwner($token)->toArray();
        } catch (IdentityProviderException | OidcException $exception) {
            throw new OidcException("OIDC token exchange failed: " . $exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new OidcException("OIDC token exchange failed.", 0, $exception);
        }

        return $this->mapIdentity($owner);
    }

    /**
     * @param array<string, mixed> $owner
     */
    private function mapIdentity(array $owner): OidcIdentity
    {
        $email = $owner["email"] ?? null;

        if (!is_string($email) || trim($email) === "") {
            throw new OidcException("OIDC userinfo did not include an email address.");
        }

        $subject = $owner["sub"] ?? null;
        $name = $owner["name"] ?? null;

        return new OidcIdentity(
            is_string($subject) ? $subject : "",
            $email,
            $this->isTrue($owner["email_verified"] ?? false),
            is_string($name) ? $name : null,
        );
    }

    /**
     * @param array<string, mixed> $tokenValues
     */
    private function assertNonce(array $tokenValues, string $expectedNonce): void
    {
        $idToken = $tokenValues["id_token"] ?? null;

        if (!is_string($idToken)) {
            return;
        }

        $claims = $this->decodeJwtPayload($idToken);
        $nonce = $claims["nonce"] ?? null;

        if ($nonce === null) {
            return;
        }

        if (!is_string($nonce) || $expectedNonce === "" || !hash_equals($expectedNonce, $nonce)) {
            throw new OidcException("OIDC id_token nonce mismatch.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode(".", $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $decoded = base64_decode(strtr($parts[1], "-_", "+/"), true);

        if ($decoded === false) {
            return [];
        }

        try {
            $claims = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($claims)) {
            return [];
        }

        $stringKeyed = [];

        foreach ($claims as $key => $value) {
            $stringKeyed[(string)$key] = $value;
        }

        return $stringKeyed;
    }

    private function s256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash("sha256", $verifier, true)), "+/", "-_"), "=");
    }

    private function isTrue(mixed $value): bool
    {
        return $value === true || $value === "true";
    }

    private function provider(): GenericProvider
    {
        return $this->provider ??= new GenericProvider([
            "clientId" => $this->config->clientId,
            "clientSecret" => $this->config->clientSecret,
            "redirectUri" => $this->config->redirectUrl,
            "urlAuthorize" => $this->config->authorizationUrl,
            "urlAccessToken" => $this->config->tokenUrl,
            "urlResourceOwnerDetails" => $this->config->userInfoUrl,
            "scopes" => explode(" ", $this->config->scopes),
        ]);
    }
}
