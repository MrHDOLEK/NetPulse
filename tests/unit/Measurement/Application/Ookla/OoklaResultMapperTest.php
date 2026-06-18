<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Application\Ookla;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Application\Ookla\DefaultOoklaResultMapper;
use App\Measurement\Application\Ookla\OoklaResult;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OoklaResultMapperTest extends TestCase
{
    private const string MEASUREMENT_ID = '11111111-1111-4111-8111-111111111111';
    private const string PROBE_ID = '22222222-2222-4222-8222-222222222222';
    private const string CONNECTION_ID = '33333333-3333-4333-8333-333333333333';
    private const string RECORDED_AT = '2026-06-06T10:00:00+00:00';

    /**
     * @return iterable<string,array{0:array<string,mixed>}>
     */
    public static function failedResultProvider(): iterable
    {
        yield 'error type with no metrics' => [[
            'type' => 'error',
            'error' => 'Configuration - Could not retrieve or read configuration',
        ]];

        yield 'result type but every metric block missing' => [[
            'type' => 'result',
            'server' => ['id' => 1, 'name' => 'S', 'location' => 'L', 'host' => 'h'],
            'isp' => 'ISP',
        ]];

        yield 'result type with ping but no download/upload bandwidth' => [[
            'type' => 'result',
            'ping' => ['latency' => 9.0],
            'isp' => 'ISP',
        ]];

        yield 'result type with bandwidth but no ping latency' => [[
            'type' => 'result',
            'download' => ['bandwidth' => 1_000_000, 'bytes' => 10],
            'upload' => ['bandwidth' => 500_000, 'bytes' => 5],
            'isp' => 'ISP',
        ]];
    }

    public function testMapsCompletedResultIntoMeasurementWithValueObjects(): void
    {
        $measurement = $this->map($this->completedOoklaJson());

        self::assertTrue($measurement->id()->equals(new MeasurementId(self::MEASUREMENT_ID)));
        self::assertTrue($measurement->probeId()->equals(new ProbeId(self::PROBE_ID)));
        self::assertTrue($measurement->connectionId()->equals(new ConnectionId(self::CONNECTION_ID)));
        self::assertEquals(new DateTimeImmutable(self::RECORDED_AT), $measurement->startedAt());
        self::assertEquals(new DateTimeImmutable(self::RECORDED_AT), $measurement->completedAt());
        self::assertSame(MeasurementStatus::Completed, $measurement->status());
        self::assertTrue($measurement->isScheduled());
        self::assertNull($measurement->healthy());

        $bandwidth = $measurement->bandwidth();
        self::assertNotNull($bandwidth);
        self::assertSame(117_875_000 * 8, $bandwidth->downloadBits);
        self::assertSame(23_375_000 * 8, $bandwidth->uploadBits);
        self::assertSame(1_200_000_000, $bandwidth->downloadBytes);
        self::assertSame(240_000_000, $bandwidth->uploadBytes);

        $latency = $measurement->latency();
        self::assertNotNull($latency);
        self::assertSame(12.5, $latency->ping);
        self::assertSame(11.0, $latency->pingLow);
        self::assertSame(1.2, $latency->jitter);
        self::assertSame(18.4, $latency->downloadLatencyIqm);
        self::assertSame(2.5, $latency->downloadJitter);
        self::assertSame(22.1, $latency->uploadLatencyIqm);

        $packetLoss = $measurement->packetLoss();
        self::assertNotNull($packetLoss);
        self::assertSame(0.0, $packetLoss->ratio);

        $server = $measurement->server();
        self::assertSame('12746', $server->serverId);
        self::assertSame('Orange Polska', $server->serverName);
        self::assertSame('Warsaw', $server->serverLocation);
        self::assertSame('speedtest.orange.pl:8080', $server->serverHost);
        self::assertSame('Orange Polska', $server->isp);

        self::assertSame(9_000, $measurement->downloadElapsed());
        self::assertSame(8_000, $measurement->uploadElapsed());
        self::assertSame(1_200_000_000, $measurement->dataUsedDownload());
        self::assertSame(240_000_000, $measurement->dataUsedUpload());
        self::assertSame('https://www.speedtest.net/result/c/abc-123', $measurement->resultUrl());
        self::assertSame($this->completedOoklaJson(), $measurement->rawPayload());
    }

    public function testConvertsPacketLossPercentToRatio(): void
    {
        $json = $this->completedOoklaJson();
        $json['packetLoss'] = 12.5;

        $packetLoss = $this->map($json)->packetLoss();

        self::assertNotNull($packetLoss);
        self::assertSame(0.125, $packetLoss->ratio);
    }

    /**
     * @param array<string,mixed> $json
     */
    #[DataProvider('failedResultProvider')]
    public function testMapsNonResultIntoFailedMeasurementWithNullMetrics(array $json): void
    {
        $measurement = $this->map($json);

        self::assertSame(MeasurementStatus::Failed, $measurement->status());
        self::assertNull($measurement->bandwidth());
        self::assertNull($measurement->latency());
        self::assertNull($measurement->packetLoss());
        self::assertNull($measurement->resultUrl());
        self::assertNull($measurement->healthy());
        self::assertSame(0, $measurement->downloadElapsed());
        self::assertSame(0, $measurement->uploadElapsed());
        self::assertSame(0, $measurement->dataUsedDownload());
        self::assertSame(0, $measurement->dataUsedUpload());
        self::assertSame($json, $measurement->rawPayload());
    }

    public function testFailedResultStillCapturesServerInfo(): void
    {
        $measurement = $this->map([
            'type' => 'error',
            'error' => 'boom',
            'server' => [
                'id' => 99,
                'name' => 'Edge',
                'location' => 'Berlin',
                'host' => 'edge.example',
                'port' => 5060,
            ],
            'isp' => 'DT',
        ]);

        $server = $measurement->server();
        self::assertSame('99', $server->serverId);
        self::assertSame('Edge', $server->serverName);
        self::assertSame('edge.example:5060', $server->serverHost);
        self::assertSame('DT', $server->isp);
    }

    public function testToleratesMissingServerPort(): void
    {
        $json = $this->completedOoklaJson();
        unset($json['server']['port']);

        self::assertSame('speedtest.orange.pl', $this->map($json)->server()->serverHost);
    }

    public function testMapsDirectlyConstructedEmptyResultAsFailed(): void
    {
        $mapper = new DefaultOoklaResultMapper();

        $measurement = $mapper->toMeasurement(
            new MeasurementId(self::MEASUREMENT_ID),
            new ProbeId(self::PROBE_ID),
            new ConnectionId(self::CONNECTION_ID),
            new OoklaResult(),
            false,
            new MockClock(self::RECORDED_AT)->now(),
            [],
        );

        self::assertSame(MeasurementStatus::Failed, $measurement->status());
        self::assertFalse($measurement->isScheduled());
        self::assertNull($measurement->bandwidth());
        self::assertSame('', $measurement->server()->serverId);
        self::assertSame([], $measurement->rawPayload());
    }

    /**
     * @param array<string,mixed> $json
     */
    private function map(array $json): Measurement
    {
        return MeasurementMother::fromOoklaArray(
            $json,
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new MockClock(self::RECORDED_AT)->now(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function completedOoklaJson(): array
    {
        return [
            'type' => 'result',
            'timestamp' => '2026-06-05T10:00:00Z',
            'ping' => ['jitter' => 1.2, 'latency' => 12.5, 'low' => 11.0, 'high' => 14.0],
            'download' => [
                'bandwidth' => 117_875_000,
                'bytes' => 1_200_000_000,
                'elapsed' => 9_000,
                'latency' => ['iqm' => 18.4, 'low' => 13.0, 'high' => 40.0, 'jitter' => 2.5],
            ],
            'upload' => [
                'bandwidth' => 23_375_000,
                'bytes' => 240_000_000,
                'elapsed' => 8_000,
                'latency' => ['iqm' => 22.1, 'low' => 15.0, 'high' => 55.0, 'jitter' => 3.1],
            ],
            'packetLoss' => 0.0,
            'isp' => 'Orange Polska',
            'server' => [
                'id' => 12746,
                'host' => 'speedtest.orange.pl',
                'port' => 8080,
                'name' => 'Orange Polska',
                'location' => 'Warsaw',
            ],
            'result' => [
                'id' => 'abc-123',
                'url' => 'https://www.speedtest.net/result/c/abc-123',
                'persisted' => true,
            ],
        ];
    }
}
