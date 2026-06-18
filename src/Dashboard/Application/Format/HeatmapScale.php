<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Format;

use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;

use function ceil;
use function count;
use function max;
use function min;
use function round;
use function sort;

final readonly class HeatmapScale
{
    private const int LEGEND_BREAKS = 5;
    private const int PERCENTILE = 95;

    private function __construct(
        private float $min,
        private float $max,
        private HeatmapMetric $metric,
    ) {}

    public static function forGrid(HeatmapGrid $grid, HeatmapMetric $metric): self
    {
        if ($metric === HeatmapMetric::Health) {
            return new self(0.0, 1.0, $metric);
        }

        $values = [];

        foreach ($grid as $cell) {
            if ($cell->value !== null) {
                $values[] = $cell->value;
            }
        }

        if ($values === []) {
            return new self(0.0, 1.0, $metric);
        }

        sort($values);

        $min = $values[0];
        $max = self::percentile($values, self::PERCENTILE);

        if ($max <= $min) {
            $max = $min + 1.0;
        }

        return new self($min, $max, $metric);
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    public function goodness(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $t = ($value - $this->min) / ($this->max - $this->min);
        $t = max(0.0, min(1.0, $t));

        return $this->metric->higherIsBetter() ? $t : 1.0 - $t;
    }

    public function bgStyle(?float $value): string
    {
        $goodness = $this->goodness($value);

        if ($goodness === null) {
            return 'background: var(--surface-2)';
        }

        $pct = (int) round($goodness * 100);

        return "background: color-mix(in oklab, var(--good) {$pct}%, var(--bad))";
    }

    /**
     * @return list<array{label: string, bgStyle: string}>
     */
    public function legendBreaks(): array
    {
        $breaks = [];
        $steps = self::LEGEND_BREAKS - 1;

        for ($i = 0; $i < self::LEGEND_BREAKS; ++$i) {
            $value = $this->min + (($this->max - $this->min) * ($i / $steps));

            $breaks[] = [
                'label' => $this->label($value),
                'bgStyle' => $this->bgStyle($value),
            ];
        }

        return $breaks;
    }

    /**
     * @param non-empty-list<float> $sorted ascending
     */
    private static function percentile(array $sorted, int $p): float
    {
        $count = count($sorted);
        $rank = (int) ceil(($p / 100) * $count);
        $rank = max(1, min($count, $rank));

        return $sorted[$rank - 1];
    }

    private function label(float $value): string
    {
        return match ($this->metric) {
            HeatmapMetric::Download => UnitFormatter::bitsPerSecond((int) $value),
            HeatmapMetric::Ping => UnitFormatter::seconds($value),
            HeatmapMetric::Health => UnitFormatter::ratio($value),
        };
    }
}
