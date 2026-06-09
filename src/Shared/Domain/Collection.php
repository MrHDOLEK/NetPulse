<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
abstract class Collection implements IteratorAggregate, Countable
{
    /**
     * @param list<T> $items
     */
    protected function __construct(
        /** @var list<T> */
        private array $items,
    ) {}

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
