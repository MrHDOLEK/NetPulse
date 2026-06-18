<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\Exception\RemoteWriteFailed;
use App\Metrics\Domain\RemoteWrite\RemoteWriteClient;
use App\Metrics\Infrastructure\Symfony\Config\RemoteWriteConfig;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AsAlias(id: RemoteWriteClient::class)]
final class PrometheusRemoteWriteClient implements RemoteWriteClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RemoteWriteEncoder $encoder,
        private readonly RemoteWriteConfig $config,
    ) {}

    public function write(TimeSeriesCollection $series): void
    {
        $body = $this->encoder->snappy($this->encoder->encodeWriteRequest($series));

        $headers = [
            'Content-Type' => 'application/x-protobuf',
            'Content-Encoding' => 'snappy',
            'X-Prometheus-Remote-Write-Version' => '0.1.0',
        ];

        $authHeader = $this->authorizationHeader();

        if ($authHeader !== null) {
            $headers['Authorization'] = $authHeader;
        }

        try {
            $response = $this->httpClient->request('POST', $this->config->url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw RemoteWriteFailed::transport($exception->getMessage());
        }

        if ($status < Response::HTTP_OK || $status >= Response::HTTP_MULTIPLE_CHOICES) {
            throw RemoteWriteFailed::withStatus($status, $this->safeBody($response));
        }
    }

    private function authorizationHeader(): ?string
    {
        $auth = $this->config->auth;

        if ($auth === null || $auth === '') {
            return null;
        }

        if (str_starts_with($auth, 'bearer:')) {
            return 'Bearer ' . substr($auth, strlen('bearer:'));
        }

        if (str_starts_with($auth, 'basic:')) {
            $credentials = substr($auth, strlen('basic:'));

            return 'Basic ' . base64_encode($credentials);
        }

        return null;
    }

    private function safeBody(ResponseInterface $response): string
    {
        try {
            return substr($response->getContent(false), 0, 512);
        } catch (TransportExceptionInterface) {
            return '';
        }
    }
}
