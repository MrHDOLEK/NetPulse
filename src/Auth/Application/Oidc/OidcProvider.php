<?php

declare(strict_types=1);

namespace App\Auth\Application\Oidc;

interface OidcProvider
{
    public function authorizationUrl(string $state, string $codeVerifier, string $nonce): string;

    /**
     * @throws OidcException on any token/userinfo/validation failure or missing email
     */
    public function exchange(string $code, string $codeVerifier, string $expectedNonce): OidcIdentity;
}
