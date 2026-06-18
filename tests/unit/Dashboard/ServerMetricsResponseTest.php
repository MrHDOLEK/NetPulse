<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRow;
use App\Dashboard\Application\ReadModel\ServerMetricsRowCollection;
use App\Dashboard\Application\Response\ServerMetricsResponse;
use PHPUnit\Framework\TestCase;

final class ServerMetricsResponseTest extends TestCase
{
    private const int NOW = 1_700_000_000;

    public function testToArrayShapeWithRawNumericAndLabels(): void
    {
        $row = new ServerMetricsRow(
            serverId: 'A',
            name: 'Acme Speedtest',
            location: 'Warsaw',
            avgDownloadBits: 750_000_000.0,
            avgUploadBits: 2_000_000_000.0,
            avgPingSeconds: 0.06,
            avgLossRatio: 0.01,
            testCount: 4,
            healthyCount: 3,
            lastSeenUnix: self::NOW - 7200,
        );

        $payload = ServerMetricsResponse::from(
            ServerMetricsRowCollection::of($row),
            HeatmapWindow::Month,
            self::NOW,
        )->toArray();

        self::assertSame('30d', $payload['window']);
        self::assertCount(1, $payload['rows']);

        $r = $payload['rows'][0];

        self::assertSame('A', $r['serverId']);
        self::assertSame('Acme Speedtest', $r['name']);
        self::assertSame('Warsaw', $r['location']);

        self::assertSame(750_000_000.0, $r['download']);
        self::assertSame(2_000_000_000.0, $r['upload']);
        self::assertSame(0.06, $r['ping']);
        self::assertSame(0.01, $r['loss']);
        self::assertSame(4, $r['tests']);
        self::assertSame(self::NOW - 7200, $r['lastSeenUnix']);

        self::assertStringContainsString('Mbps', $r['downloadLabel']);
        self::assertStringContainsString('Gbps', $r['uploadLabel']);
        self::assertStringContainsString('ms', $r['pingLabel']);
        self::assertStringContainsString('%', $r['lossLabel']);
        self::assertStringContainsString('%', $r['healthLabel']);
        self::assertSame('2 hours ago', $r['lastSeenLabel']);

        self::assertSame(75.0, $r['healthPct']);
        self::assertStringContainsString('75', $r['healthLabel']);

        foreach ([
            'serverId',
            'name',
            'location',
            'download',
            'downloadLabel',
            'upload',
            'uploadLabel',
            'ping',
            'pingLabel',
            'loss',
            'lossLabel',
            'tests',
            'healthPct',
            'healthLabel',
            'lastSeenUnix',
            'lastSeenLabel',
        ] as $key) {
            self::assertArrayHasKey($key, $r);
        }
    }

    public function testNullAveragesRenderRawNullAndEmDashLabels(): void
    {
        $row = new ServerMetricsRow(
            serverId: 'B',
            name: 'Globe CDN',
            location: 'Berlin',
            avgDownloadBits: null,
            avgUploadBits: null,
            avgPingSeconds: null,
            avgLossRatio: null,
            testCount: 1,
            healthyCount: 0,
            lastSeenUnix: self::NOW - 30,
        );

        $r = ServerMetricsResponse::from(
            ServerMetricsRowCollection::of($row),
            HeatmapWindow::Quarter,
            self::NOW,
        )->toArray()['rows'][0];

        self::assertNull($r['download']);
        self::assertNull($r['upload']);
        self::assertNull($r['ping']);
        self::assertNull($r['loss']);
        self::assertSame('—', $r['downloadLabel']);
        self::assertSame('—', $r['pingLabel']);
        self::assertSame('—', $r['lossLabel']);

        self::assertSame(0.0, $r['healthPct']);
        self::assertSame('just now', $r['lastSeenLabel']);
    }

    public function testWindowIsEchoedForQuarter(): void
    {
        $payload = ServerMetricsResponse::from(
            ServerMetricsRowCollection::fromList([]),
            HeatmapWindow::Quarter,
            self::NOW,
        )->toArray();

        self::assertSame('90d', $payload['window']);
        self::assertSame([], $payload['rows']);
    }
}
