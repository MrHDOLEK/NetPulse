<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapAggregator;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSample;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSampleCollection;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class HeatmapAggregatorTest extends TestCase
{
    public function testWeekdayRemapMondayIsRowZeroSundayIsRowSix(): void
    {
        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($this->instant("2026-06-08 09:00:00"), 900_000_000.0, true),
            new HeatmapSample($this->instant("2026-06-07 09:00:00"), 800_000_000.0, true),
        ]);

        $grid = (new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Download, 1);
        $by = $this->index($grid);

        self::assertSame(900_000_000.0, $by["0:9"]->value); 
        self::assertSame(800_000_000.0, $by["6:9"]->value);
    }

    public function testDownloadAveragesIgnoringNullsAndCountsAttempts(): void
    {
        $t = $this->instant("2026-06-08 10:00:00"); 
        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($t, 900_000_000.0, true),
            new HeatmapSample($t, 700_000_000.0, true),
            new HeatmapSample($t, null, false),
        ]);

        $cell = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Download, 1))["0:10"];

        self::assertSame(800_000_000.0, $cell->value); 
        self::assertSame(2, $cell->samples);
        self::assertSame(3, $cell->attempts);
    }

    public function testPingValuePassthrough(): void
    {
        $t = $this->instant("2026-06-08 11:00:00");
        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($t, 0.05, true), 
            new HeatmapSample($t, 0.03, true),
        ]);

        $cell = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Ping, 1))["0:11"];

        self::assertEqualsWithDelta(0.04, $cell->value, 1e-9);
    }

    public function testHealthDenominatorIsAllAttemptsFailuresCountAsUnhealthy(): void
    {
        $t = $this->instant("2026-06-08 12:00:00");
        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($t, null, true),
            new HeatmapSample($t, null, false), 
            new HeatmapSample($t, null, false), 
            new HeatmapSample($t, null, null),
        ]);

        $cell = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Health, 1))["0:12"];

        self::assertEqualsWithDelta(0.25, $cell->value, 1e-9); 
        self::assertSame(4, $cell->attempts);
        self::assertSame(4, $cell->samples);
    }

    public function testLowSampleSlotBelowMinIsNull(): void
    {
        $t = $this->instant("2026-06-08 13:00:00");
        $samples = HeatmapSampleCollection::fromList([new HeatmapSample($t, 900_000_000.0, true)]);

        $cell = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Download, 3))["0:13"];

        self::assertNull($cell->value);    
        self::assertSame(1, $cell->samples); 
        self::assertSame(1, $cell->attempts);
    }

    public function testMinSamplesBoundaryTwoSamplesNullThreeSamplesPopulated(): void
    {
        $twoSlot = $this->instant("2026-06-08 14:00:00"); 
        $threeSlot = $this->instant("2026-06-08 15:00:00"); 

        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($twoSlot, 900_000_000.0, true),
            new HeatmapSample($twoSlot, 700_000_000.0, true),
            new HeatmapSample($threeSlot, 900_000_000.0, true),
            new HeatmapSample($threeSlot, 600_000_000.0, true),
            new HeatmapSample($threeSlot, 300_000_000.0, true),
        ]);

        $by = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Download, 3));

        self::assertNull($by["0:14"]->value);
        self::assertSame(2, $by["0:14"]->samples);

        self::assertSame(600_000_000.0, $by["0:15"]->value); 
        self::assertSame(3, $by["0:15"]->samples);
    }

    public function testAlwaysReturns168Cells(): void
    {
        $grid = (new HeatmapAggregator())->aggregate(
            HeatmapSampleCollection::fromList([]),
            HeatmapMetric::Download,
            3,
        );

        self::assertCount(168, $grid);
    }

    public function testUtcBucketingIsNotAffectedByLocalDaylightSavingOffset(): void
    {
        $samples = HeatmapSampleCollection::fromList([
            new HeatmapSample($this->instant("2026-03-29 01:30:00"), 500_000_000.0, true),
        ]);

        $by = $this->index((new HeatmapAggregator())->aggregate($samples, HeatmapMetric::Download, 1));

        self::assertSame(500_000_000.0, $by["6:1"]->value);
    }

    private function instant(string $utc): int
    {
        return (new DateTimeImmutable($utc, new DateTimeZone("UTC")))->getTimestamp();
    }

    /**
     * @return array<string, HeatmapCell>
     */
    private function index(HeatmapGrid $grid): array
    {
        $byKey = [];

        foreach ($grid as $cell) {
            $byKey[HeatmapGrid::key($cell->dow, $cell->hour)] = $cell;
        }

        return $byKey;
    }
}
