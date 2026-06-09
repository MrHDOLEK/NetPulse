<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use PHPUnit\Framework\TestCase;

final class HeatmapQueryTest extends TestCase
{
    public function testExposesReadonlyPropertiesAsConstructed(): void
    {
        $connectionId = new ConnectionId("11111111-1111-4111-8111-111111111111");

        $query = new HeatmapQuery(HeatmapMetric::Ping, HeatmapWindow::Quarter, $connectionId);

        self::assertSame(HeatmapMetric::Ping, $query->metric);
        self::assertSame(HeatmapWindow::Quarter, $query->window);
        self::assertSame($connectionId, $query->connectionId);
    }
}
