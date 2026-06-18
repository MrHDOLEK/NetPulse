<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;

final class HeatmapAggregator
{
    public function aggregate(HeatmapSampleCollection $samples, HeatmapMetric $metric, int $minSamples): HeatmapGrid
    {
        /** @var array<string, array{sum: float, samples: int, attempts: int, healthy: int}> $acc */
        $acc = [];

        foreach ($samples as $sample) {
            $w = (int) gmdate('w', $sample->completedAtUnix);
            $dow = ($w + 6) % 7;
            $hour = (int) gmdate('G', $sample->completedAtUnix);
            $key = HeatmapGrid::key($dow, $hour);

            $bucket = $acc[$key] ?? ['sum' => 0.0, 'samples' => 0, 'attempts' => 0, 'healthy' => 0];
            ++$bucket['attempts'];

            if ($sample->value !== null) {
                $bucket['sum'] += $sample->value;
                ++$bucket['samples'];
            }

            if ($sample->healthy === true) {
                ++$bucket['healthy'];
            }

            $acc[$key] = $bucket;
        }

        $populated = [];

        foreach ($acc as $key => $bucket) {
            [$dow, $hour] = array_map(intval(...), explode(':', $key));

            if ($metric === HeatmapMetric::Health) {
                $value = $bucket['attempts'] >= $minSamples ? $bucket['healthy'] / $bucket['attempts'] : null;
                $samplesOut = $bucket['attempts'];
            } else {
                $value = $bucket['samples'] >= $minSamples ? $bucket['sum'] / $bucket['samples'] : null;
                $samplesOut = $bucket['samples'];
            }

            $populated[$key] = new HeatmapCell($dow, $hour, $value, $samplesOut, $bucket['attempts']);
        }

        return HeatmapGrid::fill($populated);
    }
}
