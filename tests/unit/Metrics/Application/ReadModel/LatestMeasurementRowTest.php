<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Application\ReadModel;

use App\Metrics\Application\ReadModel\ExpectedRow;
use App\Metrics\Application\ReadModel\LatestMeasurementRow;
use App\Metrics\Application\ReadModel\RunCountRow;
use PHPUnit\Framework\TestCase;

final class LatestMeasurementRowTest extends TestCase
{
    public function testExposesAllProjectedColumns(): void
    {
        $row = new LatestMeasurementRow(
            probeId: "11111111-1111-1111-1111-111111111111",
            probeName: "home",
            connectionId: "22222222-2222-2222-2222-222222222222",
            connectionName: "wan1",
            isp: "Acme ISP",
            serverId: "12345",
            serverName: "Acme Speedtest",
            serverLocation: "Warsaw",
            site: "home-lab",
            status: "completed",
            completedAtUnix: 1717545600,
            downloadBits: 950000000,
            uploadBits: 480000000,
            pingSeconds: 0.012,
            jitterSeconds: 0.0021,
            packetLossRatio: 0.0,
            downloadLatencyIqmSeconds: 0.015,
            uploadLatencyIqmSeconds: 0.018,
            dataUsedBytes: 123456789,
            healthy: true,
        );

        self::assertSame("11111111-1111-1111-1111-111111111111", $row->probeId);
        self::assertSame("home", $row->probeName);
        self::assertSame("wan1", $row->connectionName);
        self::assertSame("completed", $row->status);
        self::assertSame(1717545600, $row->completedAtUnix);
        self::assertSame(950000000, $row->downloadBits);
        self::assertSame(0.012, $row->pingSeconds);
        self::assertSame("home-lab", $row->site);
        self::assertTrue($row->healthy);
    }

    public function testRunCountRowAndExpectedRowAreImmutableCarriers(): void
    {
        $runs = new RunCountRow(
            probeId: "11111111-1111-1111-1111-111111111111",
            probeName: "home",
            connectionId: "22222222-2222-2222-2222-222222222222",
            connectionName: "wan1",
            status: "failed",
            count: 3,
        );
        $expected = new ExpectedRow(
            connectionId: "22222222-2222-2222-2222-222222222222",
            connectionName: "wan1",
            probeName: "home",
            expectedDownloadBits: 1000000000,
            expectedUploadBits: 500000000,
        );

        self::assertSame(3, $runs->count);
        self::assertSame("failed", $runs->status);
        self::assertSame(1000000000, $expected->expectedDownloadBits);
        self::assertSame(500000000, $expected->expectedUploadBits);
    }
}
