<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\Infrastructure\Oidc\OidcConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OidcConfigTest extends TestCase
{
    private const string CLIENT_ID = 'client-id';
    private const string CLIENT_SECRET = 'client-secret';
    private const string AUTH_URL = 'https://idp.example/authorize';
    private const string TOKEN_URL = 'https://idp.example/token';
    private const string USERINFO_URL = 'https://idp.example/userinfo';
    private const string REDIRECT_URL = 'https://app.example/login/oidc/callback';

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingPieceProvider(): iterable
    {
        yield 'no client id' => [['clientId' => '']];
        yield 'no client secret' => [['clientSecret' => '']];
        yield 'no authorization url' => [['authorizationUrl' => '']];
        yield 'no token url' => [['tokenUrl' => '']];
        yield 'no userinfo url' => [['userInfoUrl' => '']];
        yield 'whitespace-only client id' => [['clientId' => '   ']];
    }

    public function testIsEnabledWhenEverythingIsConfigured(): void
    {
        self::assertTrue($this->config()->isEnabled());
    }

    /**
     * @param array{
     *     clientId?: string,
     *     clientSecret?: string,
     *     authorizationUrl?: string,
     *     tokenUrl?: string,
     *     userInfoUrl?: string,
     * } $override
     */
    #[DataProvider('missingPieceProvider')]
    public function testIsDisabledWhenAnyRequiredPieceIsMissing(array $override): void
    {
        self::assertFalse($this->config($override)->isEnabled());
    }

    public function testIsDisabledWhenNothingIsConfigured(): void
    {
        $config = new OidcConfig('', '', '', '', '', '', '', '');

        self::assertFalse($config->isEnabled());
    }

    public function testDisplayNameUsesConfiguredName(): void
    {
        $config = $this->config(['name' => 'Company SSO']);

        self::assertSame('Company SSO', $config->displayName());
    }

    public function testDisplayNameFallsBackToSso(): void
    {
        self::assertSame('SSO', $this->config(['name' => ''])->displayName());
        self::assertSame('SSO', $this->config(['name' => '   '])->displayName());
    }

    public function testScopesDefaultToOpenidEmailProfile(): void
    {
        self::assertSame('openid email profile', $this->config(['scopes' => ''])->scopes);
    }

    /**
     * @param array<string, string> $override
     */
    private function config(array $override = []): OidcConfig
    {
        $values = $override
        + [
            'clientId' => self::CLIENT_ID,
            'clientSecret' => self::CLIENT_SECRET,
            'authorizationUrl' => self::AUTH_URL,
            'tokenUrl' => self::TOKEN_URL,
            'userInfoUrl' => self::USERINFO_URL,
            'redirectUrl' => self::REDIRECT_URL,
            'scopes' => 'openid email profile',
            'name' => 'Company SSO',
        ];

        return new OidcConfig(
            $values['clientId'],
            $values['clientSecret'],
            $values['authorizationUrl'],
            $values['tokenUrl'],
            $values['userInfoUrl'],
            $values['redirectUrl'],
            $values['scopes'],
            $values['name'],
        );
    }
}
