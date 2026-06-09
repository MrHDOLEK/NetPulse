<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<DegradedRow>
 */
final class DegradedRowCollection extends Collection
{
    public static function of(DegradedRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<DegradedRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<DegradedRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
