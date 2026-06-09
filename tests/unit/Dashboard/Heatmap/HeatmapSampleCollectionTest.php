<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSample;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSampleCollection;
use PHPUnit\Framework\TestCase;

final class HeatmapSampleCollectionTest extends TestCase
{
    public function testFromListCountsIteratesAndRoundTrips(): void
    {
        $first = new HeatmapSample(1_000, 900_000_000.0, true);
        $second = new HeatmapSample(2_000, null, false);

        $collection = HeatmapSampleCollection::fromList([$first, $second]);

        self::assertCount(2, $collection);
        self::assertSame([$first, $second], $collection->toArray());

        $iterated = [];

        foreach ($collection as $sample) {
            $iterated[] = $sample;
        }
        self::assertSame([$first, $second], $iterated);
    }

    public function testSampleExposesReadonlyProperties(): void
    {
        $sample = new HeatmapSample(1_717_000_000, 0.05, null);

        self::assertSame(1_717_000_000, $sample->completedAtUnix);
        self::assertSame(0.05, $sample->value);
        self::assertNull($sample->healthy);
    }
}
