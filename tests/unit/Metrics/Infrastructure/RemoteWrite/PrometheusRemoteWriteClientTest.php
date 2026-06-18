<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\LabelCollection;
use App\Metrics\Domain\RemoteWrite\Collection\SampleCollection;
use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\Exception\RemoteWriteFailed;
use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use App\Metrics\Infrastructure\RemoteWrite\PrometheusRemoteWriteClient;
use App\Metrics\Infrastructure\RemoteWrite\RemoteWriteEncoder;
use App\Metrics\Infrastructure\Symfony\Config\RemoteWriteConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PrometheusRemoteWriteClientTest extends TestCase
{
    public function testPostsSnappyProtobufWithRequiredHeaders(): void
    {
        $captured = [];

        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured['method'] = $method;
            $captured['url'] = $url;
            $captured['headers'] = $options['normalized_headers'] ?? $options['headers'] ?? [];
            $captured['body'] = $options['body'] ?? '';

            return new MockResponse('', ['http_code' => 204]);
        });

        $config = new RemoteWriteConfig(
            enabled: true,
            url: 'https://metrics.example.com/api/v1/write',
            auth: 'basic:user:pass',
            extraLabels: 'env=prod',
        );

        $client = new PrometheusRemoteWriteClient($mock, new RemoteWriteEncoder(), $config);

        $client->write(TimeSeriesCollection::of(
            new TimeSeries(
                LabelCollection::of(new Label('__name__', 'netpulse_up'), new Label('probe', 'home')),
                SampleCollection::of(new Sample(1.0, 1_717_000_000_000)),
            ),
        ));

        self::assertSame('POST', $captured['method']);
        self::assertSame('https://metrics.example.com/api/v1/write', $captured['url']);

        $headerBlob = strtolower(implode("\n", $this->flattenHeaders($captured['headers'])));
        self::assertStringContainsString('content-encoding: snappy', $headerBlob);
        self::assertStringContainsString('x-prometheus-remote-write-version: 0.1.0', $headerBlob);
        self::assertStringContainsString('content-type: application/x-protobuf', $headerBlob);
        self::assertStringContainsString('authorization: basic ', $headerBlob);
        self::assertNotSame('', $captured['body']);
    }

    public function testThrowsRemoteWriteFailedOnNon2xx(): void
    {
        $mock = new MockHttpClient(static fn(): MockResponse => new MockResponse('boom', ['http_code' => 500]));

        $config = new RemoteWriteConfig(
            enabled: true,
            url: 'https://metrics.example.com/api/v1/write',
            auth: null,
            extraLabels: '',
        );

        $client = new PrometheusRemoteWriteClient($mock, new RemoteWriteEncoder(), $config);

        $this->expectException(RemoteWriteFailed::class);

        $client->write(TimeSeriesCollection::of(
            new TimeSeries(
                LabelCollection::of(new Label('__name__', 'netpulse_up')),
                SampleCollection::of(new Sample(1.0, 1)),
            ),
        ));
    }

    public function testAppliesBearerAuth(): void
    {
        $captured = [];

        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured['headers'] = $options['normalized_headers'] ?? $options['headers'] ?? [];

            return new MockResponse('', ['http_code' => 200]);
        });

        $config = new RemoteWriteConfig(
            enabled: true,
            url: 'https://metrics.example.com/api/v1/write',
            auth: 'bearer:secret-token',
            extraLabels: '',
        );

        $client = new PrometheusRemoteWriteClient($mock, new RemoteWriteEncoder(), $config);
        $client->write(TimeSeriesCollection::of(
            new TimeSeries(
                LabelCollection::of(new Label('__name__', 'netpulse_up')),
                SampleCollection::of(new Sample(1.0, 1)),
            ),
        ));

        $headerBlob = strtolower(implode("\n", $this->flattenHeaders($captured['headers'])));
        self::assertStringContainsString('authorization: bearer secret-token', $headerBlob);
    }

    /**
     * @param array<mixed> $headers
     *
     * @return list<string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $flat[] = is_string($key) ? "{$key}: {$item}" : (string) $item;
                }

                continue;
            }

            $flat[] = is_string($key) ? "{$key}: {$value}" : (string) $value;
        }

        return $flat;
    }
}
