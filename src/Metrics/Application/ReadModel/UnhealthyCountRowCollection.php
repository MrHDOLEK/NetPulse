<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<UnhealthyCountRow>
 */
final class UnhealthyCountRowCollection extends Collection
{
    public static function of(UnhealthyCountRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<UnhealthyCountRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<UnhealthyCountRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
