<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\Infrastructure\Oidc\LeagueOidcProvider;
use App\Auth\Infrastructure\Oidc\OidcConfig;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function hash;
use function parse_str;
use function parse_url;
use function rtrim;
use function strtr;

use const PHP_URL_QUERY;

final class LeagueOidcProviderTest extends TestCase
{
    public function testAuthorizationUrlCarriesStatePkceNonceAndScopes(): void
    {
        $provider = new LeagueOidcProvider(
            new OidcConfig(
                'client-id',
                'client-secret',
                'https://idp.example/authorize',
                'https://idp.example/token',
                'https://idp.example/userinfo',
                'https://app.example/login/oidc/callback',
                'openid email profile',
                'Company SSO',
            ),
        );

        $url = $provider->authorizationUrl('the-state', 'the-verifier-0123456789', 'the-nonce');

        self::assertStringStartsWith('https://idp.example/authorize?', $url);

        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        self::assertSame('the-state', $params['state'] ?? null);
        self::assertSame('the-nonce', $params['nonce'] ?? null);
        self::assertSame('S256', $params['code_challenge_method'] ?? null);
        self::assertSame('openid email profile', $params['scope'] ?? null);
        self::assertSame('client-id', $params['client_id'] ?? null);
        self::assertSame('code', $params['response_type'] ?? null);
        self::assertSame('https://app.example/login/oidc/callback', $params['redirect_uri'] ?? null);

        $expectedChallenge = rtrim(
            strtr(base64_encode(hash('sha256', 'the-verifier-0123456789', true)), '+/', '-_'),
            '=',
        );
        self::assertSame($expectedChallenge, $params['code_challenge'] ?? null);
    }
}
