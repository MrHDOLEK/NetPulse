<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Bucketing;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<MeasurementSample>
 */
final class MeasurementSampleCollection extends Collection
{
    public static function of(MeasurementSample ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @param list<MeasurementSample> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<MeasurementSample>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
