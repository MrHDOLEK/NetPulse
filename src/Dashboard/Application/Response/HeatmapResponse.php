<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Dashboard\Application\Format\HeatmapScale;
use App\Dashboard\Application\Format\UnitFormatter;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapCell;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;

use function sprintf;
use function str_pad;

use const STR_PAD_LEFT;

final readonly class HeatmapResponse
{
    private const array WEEKDAYS = [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday",
    ];

    /**
     * @param list<array{
     *     dow: int,
     *     hour: int,
     *     value: float|null,
     *     valueLabel: string,
     *     samples: int,
     *     attempts: int,
     *     bgStyle: string,
     *     aria: string,
     * }> $cells
     * @param list<array{label: string, bgStyle: string}> $legend
     */
    private function __construct(
        public string $metric,
        public string $window,
        public string $connectionId,
        public string $unit,
        public float $scaleMin,
        public float $scaleMax,
        public array $legend,
        public array $cells,
    ) {}

    public static function fromGrid(HeatmapQuery $query, HeatmapGrid $grid, HeatmapScale $scale): self
    {
        $cells = [];

        foreach ($grid as $cell) {
            $cells[] = self::cell($query->metric, $scale, $cell);
        }

        return new self(
            $query->metric->value,
            $query->window->value,
            $query->connectionId->toString(),
            $query->metric->unit(),
            $scale->min(),
            $scale->max(),
            $scale->legendBreaks(),
            $cells,
        );
    }

    /**
     * @return array{
     *     metric: string,
     *     window: string,
     *     connectionId: string,
     *     unit: string,
     *     scale: array{min: float, max: float},
     *     legend: list<array{label: string, bgStyle: string}>,
     *     cells: list<array{
     *         dow: int,
     *         hour: int,
     *         value: float|null,
     *         valueLabel: string,
     *         samples: int,
     *         attempts: int,
     *         bgStyle: string,
     *         aria: string,
     *     }>,
     * }
     */
    public function toArray(): array
    {
        return [
            "metric" => $this->metric,
            "window" => $this->window,
            "connectionId" => $this->connectionId,
            "unit" => $this->unit,
            "scale" => [
                "min" => $this->scaleMin,
                "max" => $this->scaleMax,
            ],
            "legend" => $this->legend,
            "cells" => $this->cells,
        ];
    }

    /**
     * @return array{
     *     dow: int,
     *     hour: int,
     *     value: float|null,
     *     valueLabel: string,
     *     samples: int,
     *     attempts: int,
     *     bgStyle: string,
     *     aria: string,
     * }
     */
    private static function cell(HeatmapMetric $metric, HeatmapScale $scale, HeatmapCell $cell): array
    {
        $valueLabel = self::valueLabel($metric, $cell->value);

        return [
            "dow" => $cell->dow,
            "hour" => $cell->hour,
            "value" => $cell->value,
            "valueLabel" => $valueLabel,
            "samples" => $cell->samples,
            "attempts" => $cell->attempts,
            "bgStyle" => $scale->bgStyle($cell->value),
            "aria" => self::aria($cell, $valueLabel),
        ];
    }

    private static function valueLabel(HeatmapMetric $metric, ?float $value): string
    {
        return match ($metric) {
            HeatmapMetric::Download => UnitFormatter::bitsPerSecond($value === null ? null : (int)$value),
            HeatmapMetric::Ping => UnitFormatter::seconds($value),
            HeatmapMetric::Health => UnitFormatter::ratio($value),
        };
    }

    private static function aria(HeatmapCell $cell, string $valueLabel): string
    {
        $weekday = self::WEEKDAYS[$cell->dow];
        $hour = str_pad((string)$cell->hour, 2, "0", STR_PAD_LEFT);
        $reading = $cell->value === null ? "no data" : $valueLabel;

        return sprintf("%s %s:00 — %s, %d samples", $weekday, $hour, $reading, $cell->samples);
    }
}
