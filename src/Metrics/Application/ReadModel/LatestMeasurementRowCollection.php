<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<LatestMeasurementRow>
 */
final class LatestMeasurementRowCollection extends Collection
{
    public static function of(LatestMeasurementRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<LatestMeasurementRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<LatestMeasurementRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
