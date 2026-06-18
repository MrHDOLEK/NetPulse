<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Settings\Infrastructure\Oidc\OidcDiscoveryProbe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class OidcDiscoveryProbeTest extends TestCase
{
    public function testBareIssuerIsExpandedToWellKnownAndPassesWhenComplete(): void
    {
        $captured = [];

        $client = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
            $captured['url'] = $url;

            return new MockResponse(self::completeDocument(), ['http_code' => 200]);
        });

        $result = new OidcDiscoveryProbe($client)->probe('https://idp.example');

        self::assertTrue($result->ok, $result->message);
        self::assertSame('https://idp.example/.well-known/openid-configuration', $captured['url']);
    }

    public function testFullDiscoveryUrlIsUsedAsIs(): void
    {
        $captured = [];

        $client = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
            $captured['url'] = $url;

            return new MockResponse(self::completeDocument(), ['http_code' => 200]);
        });

        $url = 'https://idp.example/.well-known/openid-configuration';
        $result = new OidcDiscoveryProbe($client)->probe($url);

        self::assertTrue($result->ok);
        self::assertSame($url, $captured['url']);
    }

    public function testMissingEndpointFails(): void
    {
        $document = json_encode([
            'issuer' => 'https://idp.example',
            'authorization_endpoint' => 'https://idp.example/authorize',

            'userinfo_endpoint' => 'https://idp.example/userinfo',
        ], JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(new MockResponse($document, ['http_code' => 200]));

        $result = new OidcDiscoveryProbe($client)->probe('https://idp.example');

        self::assertFalse($result->ok);
        self::assertStringContainsString('token_endpoint', $result->message);
    }

    public function testHttpErrorFails(): void
    {
        $client = new MockHttpClient(new MockResponse('nope', ['http_code' => 404]));

        $result = new OidcDiscoveryProbe($client)->probe('https://idp.example');

        self::assertFalse($result->ok);
        self::assertStringContainsString('404', $result->message);
    }

    public function testNonJsonBodyFails(): void
    {
        $client = new MockHttpClient(new MockResponse('<html>not json</html>', ['http_code' => 200]));

        $result = new OidcDiscoveryProbe($client)->probe('https://idp.example');

        self::assertFalse($result->ok);
    }

    public function testBlankInputFailsFastWithoutRequest(): void
    {
        $called = false;
        $client = new MockHttpClient(function () use (&$called): MockResponse {
            $called = true;

            return new MockResponse('', ['http_code' => 200]);
        });

        $result = new OidcDiscoveryProbe($client)->probe('   ');

        self::assertFalse($result->ok);
        self::assertFalse($called, 'a blank input must not trigger an HTTP request');
    }

    public function testNonUrlInputFailsFast(): void
    {
        $called = false;
        $client = new MockHttpClient(function () use (&$called): MockResponse {
            $called = true;

            return new MockResponse('', ['http_code' => 200]);
        });

        $result = new OidcDiscoveryProbe($client)->probe('idp.example');

        self::assertFalse($result->ok);
        self::assertFalse($called, 'input without a scheme must not trigger an HTTP request');
    }

    private static function completeDocument(): string
    {
        return json_encode([
            'issuer' => 'https://idp.example',
            'authorization_endpoint' => 'https://idp.example/authorize',
            'token_endpoint' => 'https://idp.example/token',
            'userinfo_endpoint' => 'https://idp.example/userinfo',
        ], JSON_THROW_ON_ERROR);
    }
}
