<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Scheduling\Domain\ValueObject\DueTask;
use App\Shared\Domain\Collection;

use function array_values;

/**
 * @extends Collection<DueTask>
 */
final class DueTaskCollection extends Collection
{
    public static function of(DueTask ...$tasks): self
    {
        return new self(array_values($tasks));
    }

    /**
     * @param list<DueTask> $tasks
     */
    public static function fromList(array $tasks): self
    {
        return new self($tasks);
    }

    /**
     * @return list<DueTask>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
