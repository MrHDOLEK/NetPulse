<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<HeatmapSample>
 */
final class HeatmapSampleCollection extends Collection
{
    /**
     * @param list<HeatmapSample> $samples
     */
    public static function fromList(array $samples): self
    {
        return new self($samples);
    }

    /**
     * @return list<HeatmapSample>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
