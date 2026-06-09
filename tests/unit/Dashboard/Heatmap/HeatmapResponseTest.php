<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Heatmap;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\Format\HeatmapScale;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use App\Dashboard\Application\Response\HeatmapResponse;
use PHPUnit\Framework\TestCase;

final class HeatmapResponseTest extends TestCase
{
    private const string CONN = "aaaaaaaa-0000-0000-0000-000000000001";

    public function testToArrayCarriesTheFullEnvelope(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();

        foreach (["metric", "window", "connectionId", "unit", "scale", "legend", "cells"] as $key) {
            self::assertArrayHasKey($key, $payload);
        }

        self::assertSame("download", $payload["metric"]);
        self::assertSame("30d", $payload["window"]);
        self::assertSame(self::CONN, $payload["connectionId"]);
        self::assertSame("Mbps", $payload["unit"]);
        self::assertArrayHasKey("min", $payload["scale"]);
        self::assertArrayHasKey("max", $payload["scale"]);
        self::assertNotEmpty($payload["legend"]);
    }

    public function testGridAlwaysSerialisesAll168Cells(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();

        self::assertCount(168, $payload["cells"]);
    }

    public function testPopulatedCellCarriesEveryKey(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();
        $cell = $this->cellAt($payload["cells"], 0, 9);

        foreach (["dow", "hour", "value", "valueLabel", "samples", "attempts", "bgStyle", "aria"] as $key) {
            self::assertArrayHasKey($key, $cell);
        }

        self::assertSame(0, $cell["dow"]);
        self::assertSame(9, $cell["hour"]);
        self::assertSame(900_000_000.0, $cell["value"]);
        self::assertSame(12, $cell["samples"]);
        self::assertSame(12, $cell["attempts"]);
    }

    public function testPopulatedMondayNineAmDownloadCellAriaIsSelfDescribing(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();
        $cell = $this->cellAt($payload["cells"], 0, 9);

        self::assertStringContainsString("Monday 09:00", $cell["aria"]);
        self::assertStringContainsString((string)$cell["valueLabel"], $cell["aria"]);
        self::assertStringContainsString("samples", $cell["aria"]);
        self::assertStringContainsString("Mbps", (string)$cell["valueLabel"]);
    }

    public function testEmptyCellRendersTheNoDataOffRamp(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();

        $cell = $this->cellAt($payload["cells"], 0, 0);

        self::assertNull($cell["value"]);
        self::assertSame("—", $cell["valueLabel"]);
        self::assertSame("background: var(--surface-2)", $cell["bgStyle"]);
        self::assertStringContainsString("no data", $cell["aria"]);
        self::assertStringContainsString("Monday 00:00", $cell["aria"]);
    }

    public function testSundayMapsToTheFullWeekdayName(): void
    {
        $payload = $this->buildResponse(HeatmapMetric::Download)->toArray();
        $cell = $this->cellAt($payload["cells"], 6, 0);

        self::assertStringContainsString("Sunday 00:00", $cell["aria"]);
    }

    public function testHealthCellLabelUsesRatioFormatting(): void
    {
        $populated = [
            HeatmapGrid::key(0, 9) => new HeatmapCell(0, 9, 0.5, 8, 8),
        ];
        $grid = HeatmapGrid::fill($populated);
        $query = new HeatmapQuery(HeatmapMetric::Health, HeatmapWindow::Month, new ConnectionId(self::CONN));
        $scale = HeatmapScale::forGrid($grid, HeatmapMetric::Health);

        $cell = $this->cellAt(HeatmapResponse::fromGrid($query, $grid, $scale)->toArray()["cells"], 0, 9);

        self::assertSame("50 %", $cell["valueLabel"]);
        self::assertStringContainsString("50 %", $cell["aria"]);
    }

    private function buildResponse(HeatmapMetric $metric): HeatmapResponse
    {
        $populated = [
            HeatmapGrid::key(0, 9) => new HeatmapCell(0, 9, 900_000_000.0, 12, 12),
        ];
        $grid = HeatmapGrid::fill($populated);
        $query = new HeatmapQuery($metric, HeatmapWindow::Month, new ConnectionId(self::CONN));
        $scale = HeatmapScale::forGrid($grid, $metric);

        return HeatmapResponse::fromGrid($query, $grid, $scale);
    }

    /**
     * @param list<array<string, mixed>> $cells
     *
     * @return array<string, mixed>
     */
    private function cellAt(array $cells, int $dow, int $hour): array
    {
        foreach ($cells as $cell) {
            if ($cell["dow"] === $dow && $cell["hour"] === $hour) {
                return $cell;
            }
        }

        self::fail(sprintf("No cell at dow=%d hour=%d", $dow, $hour));
    }
}
