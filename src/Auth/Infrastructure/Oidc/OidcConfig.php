<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Oidc;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function trim;

final readonly class OidcConfig
{
    public string $clientId;
    public string $clientSecret;
    public string $authorizationUrl;
    public string $tokenUrl;
    public string $userInfoUrl;
    public string $redirectUrl;
    public string $scopes;
    public string $name;

    public function __construct(
        #[Autowire("%env(string:OIDC_CLIENT_ID)%")]
        string $clientId,
        #[Autowire("%env(string:OIDC_CLIENT_SECRET)%")]
        string $clientSecret,
        #[Autowire("%env(string:OIDC_AUTHORIZATION_URL)%")]
        string $authorizationUrl,
        #[Autowire("%env(string:OIDC_TOKEN_URL)%")]
        string $tokenUrl,
        #[Autowire("%env(string:OIDC_USERINFO_URL)%")]
        string $userInfoUrl,
        #[Autowire("%env(string:OIDC_REDIRECT_URL)%")]
        string $redirectUrl,
        #[Autowire("%env(string:OIDC_SCOPES)%")]
        string $scopes,
        #[Autowire("%env(string:OIDC_NAME)%")]
        string $name,
    ) {
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->authorizationUrl = trim($authorizationUrl);
        $this->tokenUrl = trim($tokenUrl);
        $this->userInfoUrl = trim($userInfoUrl);
        $this->redirectUrl = trim($redirectUrl);

        $trimmedScopes = trim($scopes);
        $this->scopes = $trimmedScopes === "" ? "openid email profile" : $trimmedScopes;

        $trimmedName = trim($name);
        $this->name = $trimmedName === "" ? "SSO" : $trimmedName;
    }

    public function isEnabled(): bool
    {
        return $this->clientId !== ""
            && $this->clientSecret !== ""
            && $this->authorizationUrl !== ""
            && $this->tokenUrl !== ""
            && $this->userInfoUrl !== "";
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
