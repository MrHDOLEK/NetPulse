<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<ExpectedRow>
 */
final class ExpectedRowCollection extends Collection
{
    public static function of(ExpectedRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<ExpectedRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<ExpectedRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
