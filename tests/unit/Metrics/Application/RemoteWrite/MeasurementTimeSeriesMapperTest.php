<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Application\RemoteWrite;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Metrics\Application\RemoteWrite\MeasurementTimeSeriesMapper;
use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MeasurementTimeSeriesMapperTest extends TestCase
{
    private const string MEASUREMENT_ID = '11111111-1111-4111-8111-111111111111';
    private const string PROBE_ID = '22222222-2222-4222-8222-222222222222';
    private const string CONNECTION_ID = '33333333-3333-4333-8333-333333333333';
    private const string PROBE_NAME = 'home';
    private const string CONNECTION_NAME = 'wan1';
    private const string SITE = 'warsaw';
    private const string RECORDED_AT = '2026-06-06T10:00:00+00:00';

    public function testMapsCompletedMeasurementToGaugeSeriesWithMillisecondTimestamp(): void
    {
        $measurement = MeasurementMother::fromOoklaArray(
            $this->completedOoklaJson(),
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new MockClock(self::RECORDED_AT)->now(),
        );

        $mapper = new MeasurementTimeSeriesMapper('env=prod');

        $series = $mapper->map($measurement, $this->connection(), $this->probe());

        $byName = $this->indexByName($series);

        self::assertArrayHasKey('netpulse_up', $byName);
        self::assertSame(1.0, $byName['netpulse_up']->samples->toArray()[0]->value);

        self::assertArrayHasKey('netpulse_download_bits_per_second', $byName);
        self::assertSame(94_000_000.0, $byName['netpulse_download_bits_per_second']->samples->toArray()[0]->value);

        self::assertArrayHasKey('netpulse_ping_seconds', $byName);

        self::assertSame(0.0125, $byName['netpulse_ping_seconds']->samples->toArray()[0]->value);

        self::assertArrayHasKey('netpulse_packet_loss_ratio', $byName);

        $expectedMs = (int) $measurement->completedAt()->format('Uv');
        self::assertSame($expectedMs, $byName['netpulse_up']->samples->toArray()[0]->timestampMs);

        foreach ($series as $timeSeries) {
            $labels = $this->labelMap($timeSeries);
            self::assertSame(self::PROBE_NAME, $labels['probe']);
            self::assertSame(self::CONNECTION_NAME, $labels['connection']);
            self::assertSame(self::SITE, $labels['site']);
            self::assertSame('S', $labels['server_name']);
            self::assertSame('1', $labels['server_id']);
            self::assertSame('Orange Polska', $labels['isp']);
            self::assertSame('prod', $labels['env']);
            self::assertArrayHasKey('__name__', $labels);
        }
    }

    public function testFailedMeasurementMapsOnlyToUpZero(): void
    {
        $measurement = MeasurementMother::fromOoklaArray(
            ['type' => 'result', 'error' => 'timeout'],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            false,
            new MockClock(self::RECORDED_AT)->now(),
        );

        $mapper = new MeasurementTimeSeriesMapper('');

        $series = $mapper->map($measurement, $this->connection(), $this->probe());
        $byName = $this->indexByName($series);

        self::assertArrayHasKey('netpulse_up', $byName);
        self::assertSame(0.0, $byName['netpulse_up']->samples->toArray()[0]->value);
        self::assertArrayNotHasKey('netpulse_download_bits_per_second', $byName);
        self::assertArrayNotHasKey('netpulse_ping_seconds', $byName);

        $labels = $this->labelMap($byName['netpulse_up']);
        self::assertSame(self::PROBE_NAME, $labels['probe']);
        self::assertSame(self::CONNECTION_NAME, $labels['connection']);
    }

    /**
     * @return array<string, TimeSeries>
     */
    private function indexByName(TimeSeriesCollection $series): array
    {
        $indexed = [];

        foreach ($series as $timeSeries) {
            $indexed[$this->labelMap($timeSeries)['__name__']] = $timeSeries;
        }

        return $indexed;
    }

    /**
     * @return array<string, string>
     */
    private function labelMap(TimeSeries $series): array
    {
        $map = [];

        foreach ($series->labels as $label) {
            $map[$label->name] = $label->value;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function completedOoklaJson(): array
    {
        return [
            'type' => 'result',
            'timestamp' => '2026-06-05T10:00:01Z',
            'ping' => ['jitter' => 0.5, 'latency' => 12.5, 'low' => 11.0, 'high' => 14.0],
            'download' => ['bandwidth' => 11_750_000, 'bytes' => 50_000_000, 'elapsed' => 5000],
            'upload' => ['bandwidth' => 2_000_000, 'bytes' => 10_000_000, 'elapsed' => 5000],
            'packetLoss' => 0.0,
            'isp' => 'Orange Polska',
            'server' => ['id' => 1, 'name' => 'S', 'location' => 'L', 'host' => 'h', 'ip' => '1.2.3.4'],
            'result' => ['url' => 'https://x'],
        ];
    }

    private function connection(): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION_ID),
            new ProbeId(self::PROBE_ID),
            self::CONNECTION_NAME,
            'Orange Polska',
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::fromList('12746'),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }

    private function probe(): Probe
    {
        return new Probe(
            new ProbeId(self::PROBE_ID),
            self::PROBE_NAME,
            Labels::fromArray(['site' => self::SITE]),
            'hash',
            true,
            new DateTimeImmutable(self::RECORDED_AT),
        );
    }
}
