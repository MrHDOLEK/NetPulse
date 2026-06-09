<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<MeasurementListItem>
 */
final class MeasurementListItemCollection extends Collection
{
    public static function of(MeasurementListItem ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @param list<MeasurementListItem> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<MeasurementListItem>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
