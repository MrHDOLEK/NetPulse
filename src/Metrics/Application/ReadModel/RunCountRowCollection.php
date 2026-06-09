<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<RunCountRow>
 */
final class RunCountRowCollection extends Collection
{
    public static function of(RunCountRow ...$rows): self
    {
        return new self(array_values($rows));
    }

    /**
     * @param list<RunCountRow> $rows
     */
    public static function fromList(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @return list<RunCountRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
