<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HeatmapWindowTest extends TestCase
{
    public function testFromParamMapsBackingValues(): void
    {
        self::assertSame(HeatmapWindow::Month, HeatmapWindow::fromParam("30d"));
        self::assertSame(HeatmapWindow::Quarter, HeatmapWindow::fromParam("90d"));
    }

    public function testFromParamRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HeatmapWindow::fromParam("24h");
    }

    public function testWindowSeconds(): void
    {
        self::assertSame(2_592_000, HeatmapWindow::Month->windowSeconds());
        self::assertSame(7_776_000, HeatmapWindow::Quarter->windowSeconds());
    }
}
