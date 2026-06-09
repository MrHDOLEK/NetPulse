<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\Format\HeatmapScale;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class HeatmapScaleTest extends TestCase
{
    public function testDownloadDomainIsMinToP95ClampingTheOutlier(): void
    {
        $values = [];

        for ($i = 1; $i <= 19; ++$i) {
            $values[] = (float)($i * 100_000_000);
        }

        $values[] = 10_000_000_000.0; 

        $scale = HeatmapScale::forGrid($this->gridOf(HeatmapMetric::Download, $values), HeatmapMetric::Download);

        self::assertSame(100_000_000.0, $scale->min());

        self::assertSame(1_900_000_000.0, $scale->max());
    }

    public function testHealthDomainIsFixedZeroToOne(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Health, [0.25, 0.9, 0.5]),
            HeatmapMetric::Health,
        );

        self::assertSame(0.0, $scale->min());
        self::assertSame(1.0, $scale->max());
    }

    public function testEmptyGridFallsBackToZeroToOne(): void
    {
        $scale = HeatmapScale::forGrid(HeatmapGrid::fill([]), HeatmapMetric::Download);

        self::assertSame(0.0, $scale->min());
        self::assertSame(1.0, $scale->max());
    }

    public function testMaxIsBumpedWhenDomainCollapses(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [500.0, 500.0, 500.0]),
            HeatmapMetric::Download,
        );

        self::assertSame(500.0, $scale->min());
        self::assertSame(501.0, $scale->max());
    }

    public function testGoodnessIsHighAtTheBetterEndForDownload(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [100.0, 900.0]),
            HeatmapMetric::Download,
        );

        self::assertEqualsWithDelta(1.0, (float)$scale->goodness($scale->max()), 1e-9);
        self::assertEqualsWithDelta(0.0, (float)$scale->goodness($scale->min()), 1e-9);
    }

    public function testGoodnessInvertsForPingSoLowLatencyIsGood(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Ping, [0.01, 0.2]),
            HeatmapMetric::Ping,
        );

        self::assertEqualsWithDelta(1.0, (float)$scale->goodness($scale->min()), 1e-9);
        self::assertEqualsWithDelta(0.0, (float)$scale->goodness($scale->max()), 1e-9);
    }

    public function testGoodnessClampsOutOfRangeAndPassesNullThrough(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [100.0, 200.0]),
            HeatmapMetric::Download,
        );

        self::assertNull($scale->goodness(null));
        self::assertEqualsWithDelta(0.0, (float)$scale->goodness(-50.0), 1e-9);
        self::assertEqualsWithDelta(1.0, (float)$scale->goodness(999_999.0), 1e-9);
    }

    public function testBgStyleForNullIsTheInsufficientDataOffRamp(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [100.0, 200.0]),
            HeatmapMetric::Download,
        );

        self::assertSame("background: var(--surface-2)", $scale->bgStyle(null));
    }

    public function testBgStyleForValueMatchesTheColorMixFormat(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [100.0, 900.0]),
            HeatmapMetric::Download,
        );

        $style = $scale->bgStyle(500.0);

        self::assertSame(
            1,
            preg_match('/^background: color-mix\(in oklab, var\(--good\) \d+%, var\(--bad\)\)$/', $style),
            "unexpected bgStyle: " . $style,
        );
    }

    public function testLegendBreaksAreNumericLabelledSwatches(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Download, [100_000_000.0, 900_000_000.0]),
            HeatmapMetric::Download,
        );

        $legend = $scale->legendBreaks();

        self::assertGreaterThanOrEqual(3, count($legend));

        foreach ($legend as $break) {
            self::assertArrayHasKey("label", $break);
            self::assertArrayHasKey("bgStyle", $break);
            self::assertSame(1, preg_match('/\d/', $break["label"]), "legend label not numeric: " . $break["label"]);
            self::assertSame(
                1,
                preg_match('/^background: color-mix\(in oklab, var\(--good\) \d+%, var\(--bad\)\)$/', $break["bgStyle"]),
            );
        }

        self::assertStringContainsString("bps", $legend[0]["label"]);
    }

    public function testPingLegendLabelsCarryMilliseconds(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Ping, [0.01, 0.05, 0.2]),
            HeatmapMetric::Ping,
        );

        foreach ($scale->legendBreaks() as $break) {
            self::assertStringContainsString("ms", $break["label"], "ping legend label missing ms: " . $break["label"]);
        }
    }

    public function testHealthLegendLabelsCarryPercent(): void
    {
        $scale = HeatmapScale::forGrid(
            $this->gridOf(HeatmapMetric::Health, [0.25, 0.5, 0.9]),
            HeatmapMetric::Health,
        );

        foreach ($scale->legendBreaks() as $break) {
            self::assertStringContainsString("%", $break["label"], "health legend label missing %: " . $break["label"]);
        }
    }

    public function testLegendBreaksOnAnEmptyGridStillReturnValidNumericEntries(): void
    {
        $scale = HeatmapScale::forGrid(HeatmapGrid::fill([]), HeatmapMetric::Download);

        $legend = $scale->legendBreaks();

        self::assertGreaterThanOrEqual(3, count($legend));

        foreach ($legend as $break) {
            self::assertArrayHasKey("label", $break);
            self::assertArrayHasKey("bgStyle", $break);
            self::assertSame(1, preg_match('/\d/', $break["label"]), "legend label not numeric: " . $break["label"]);
            self::assertSame(
                1,
                preg_match('/^background: color-mix\(in oklab, var\(--good\) \d+%, var\(--bad\)\)$/', $break["bgStyle"]),
            );
        }
    }

    /**
     * @param list<float> $values
     */
    private function gridOf(HeatmapMetric $metric, array $values): HeatmapGrid
    {
        $populated = [];
        $slot = 0;

        foreach ($values as $value) {
            $dow = intdiv($slot, 24);
            $hour = $slot % 24;
            $populated[HeatmapGrid::key($dow, $hour)] = new HeatmapCell($dow, $hour, $value, 3, 3);
            ++$slot;
        }

        return HeatmapGrid::fill($populated);
    }
}
