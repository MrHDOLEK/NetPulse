<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\ReadModel\ServerMetricsRow;
use App\Dashboard\Application\ReadModel\ServerMetricsRowCollection;
use PHPUnit\Framework\TestCase;

final class ServerMetricsRowCollectionTest extends TestCase
{
    public function testFromListRoundTrips(): void
    {
        $a = new ServerMetricsRow(
            serverId: '12345',
            name: 'Acme Speedtest',
            location: 'Warsaw',
            avgDownloadBits: 800_000_000.0,
            avgUploadBits: 600_000_000.0,
            avgPingSeconds: 0.04,
            avgLossRatio: 0.01,
            testCount: 3,
            healthyCount: 2,
            lastSeenUnix: 1_700_000_000,
        );
        $b = new ServerMetricsRow(
            serverId: '67890',
            name: 'Globe CDN',
            location: 'Berlin',
            avgDownloadBits: null,
            avgUploadBits: null,
            avgPingSeconds: null,
            avgLossRatio: null,
            testCount: 1,
            healthyCount: 0,
            lastSeenUnix: 1_700_000_500,
        );

        $collection = ServerMetricsRowCollection::fromList([$a, $b]);

        self::assertCount(2, $collection);
        self::assertSame([$a, $b], $collection->toArray());
        self::assertSame('12345', $collection->toArray()[0]->serverId);
        self::assertSame(800_000_000.0, $collection->toArray()[0]->avgDownloadBits);
        self::assertNull($collection->toArray()[1]->avgPingSeconds);
        self::assertSame(2, $collection->toArray()[0]->healthyCount);
    }

    public function testOfVariadicRoundTrips(): void
    {
        $a = new ServerMetricsRow(
            serverId: '12345',
            name: 'Acme Speedtest',
            location: 'Warsaw',
            avgDownloadBits: 800_000_000.0,
            avgUploadBits: 600_000_000.0,
            avgPingSeconds: 0.04,
            avgLossRatio: 0.01,
            testCount: 3,
            healthyCount: 2,
            lastSeenUnix: 1_700_000_000,
        );

        $collection = ServerMetricsRowCollection::of($a);

        self::assertCount(1, $collection);
        self::assertSame([$a], $collection->toArray());
    }
}
