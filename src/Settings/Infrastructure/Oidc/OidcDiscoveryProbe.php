<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Oidc;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function is_string;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function trim;

final readonly class OidcDiscoveryProbe
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function probe(string $issuerOrDiscoveryUrl): OidcDiscoveryResult
    {
        $url = $this->discoveryUrl(trim($issuerOrDiscoveryUrl));

        if ($url === '') {
            return OidcDiscoveryResult::failure('Enter the issuer or discovery URL first.');
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 8,
                'max_redirects' => 3,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $status = $response->getStatusCode();

            if ($status >= 400) {
                return OidcDiscoveryResult::failure('Discovery returned HTTP ' . $status . '.');
            }

            $document = $response->toArray(false);
        } catch (HttpClientException $exception) {
            return OidcDiscoveryResult::failure('Could not reach the discovery URL: ' . $exception->getMessage());
        } catch (Throwable) {
            return OidcDiscoveryResult::failure('The discovery URL did not return a valid OpenID configuration.');
        }

        return $this->evaluate($document, $url);
    }

    /**
     * @param array<mixed> $document
     */
    private function evaluate(array $document, string $url): OidcDiscoveryResult
    {
        $missing = [];

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $endpoint) {
            $value = $document[$endpoint] ?? null;

            if (!is_string($value) || trim($value) === '') {
                $missing[] = $endpoint;
            }
        }

        if ($missing !== []) {
            return OidcDiscoveryResult::failure(
                'The discovery document is missing required endpoints: ' . implode(', ', $missing) . '.',
            );
        }

        $issuer = $document['issuer'] ?? null;
        $issuerLabel = is_string($issuer) && trim($issuer) !== '' ? trim($issuer) : $url;

        return OidcDiscoveryResult::success(
            'Discovery succeeded — ' . $issuerLabel . ' advertises all required endpoints.',
        );
    }

    private function discoveryUrl(string $input): string
    {
        if ($input === '') {
            return '';
        }

        if (!str_contains($input, '://')) {
            return '';
        }

        if (str_contains($input, '/.well-known/')) {
            return $input;
        }

        if (str_ends_with($input, 'openid-configuration')) {
            return $input;
        }

        return rtrim($input, '/') . '/.well-known/openid-configuration';
    }
}
