<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<LastMeasurementRow>
 */
final class LastMeasurementRowCollection extends Collection
{
    public static function of(LastMeasurementRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<LastMeasurementRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    public function forConnection(string $connectionId): ?LastMeasurementRow
    {
        foreach ($this->toArray() as $row) {
            if ($row->connectionId->toString() === $connectionId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<LastMeasurementRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
