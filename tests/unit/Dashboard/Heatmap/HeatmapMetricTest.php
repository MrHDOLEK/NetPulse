<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HeatmapMetricTest extends TestCase
{
    public function testFromParamMapsBackingValues(): void
    {
        self::assertSame(HeatmapMetric::Download, HeatmapMetric::fromParam("download"));
        self::assertSame(HeatmapMetric::Health, HeatmapMetric::fromParam("health"));
        self::assertSame(HeatmapMetric::Ping, HeatmapMetric::fromParam("ping"));
    }

    public function testFromParamRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HeatmapMetric::fromParam("upload");
    }

    public function testUnitAndDirection(): void
    {
        self::assertSame("Mbps", HeatmapMetric::Download->unit());
        self::assertSame("%", HeatmapMetric::Health->unit());
        self::assertSame("ms", HeatmapMetric::Ping->unit());
        self::assertTrue(HeatmapMetric::Download->higherIsBetter());
        self::assertTrue(HeatmapMetric::Health->higherIsBetter());
        self::assertFalse(HeatmapMetric::Ping->higherIsBetter());
    }
}
