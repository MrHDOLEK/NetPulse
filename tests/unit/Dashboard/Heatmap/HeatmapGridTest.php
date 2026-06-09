<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use PHPUnit\Framework\TestCase;

final class HeatmapGridTest extends TestCase
{
    public function testFillMaterialisesAll168CellsInRowMajorOrder(): void
    {
        $grid = HeatmapGrid::fill([
            HeatmapGrid::key(0, 9) => new HeatmapCell(0, 9, 250.0, 12, 12),
        ]);

        self::assertCount(168, $grid);

        $cells = $grid->toArray();

        self::assertSame(0, $cells[0]->dow);
        self::assertSame(0, $cells[0]->hour);
        self::assertNull($cells[0]->value);
        self::assertSame(250.0, $cells[0 * 24 + 9]->value);
        self::assertSame(6, $cells[167]->dow);
        self::assertSame(23, $cells[167]->hour);
    }

    public function testKeyFormat(): void
    {
        self::assertSame("0:9", HeatmapGrid::key(0, 9));
        self::assertSame("6:23", HeatmapGrid::key(6, 23));
    }

    public function testEmptyCellHasNullValueAndZeroCounts(): void
    {
        $cell = HeatmapCell::empty(3, 14);

        self::assertSame(3, $cell->dow);
        self::assertSame(14, $cell->hour);
        self::assertNull($cell->value);
        self::assertSame(0, $cell->samples);
        self::assertSame(0, $cell->attempts);
    }
}
