<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<ConnectionListItem>
 */
final class ConnectionListItemCollection extends Collection
{
    public static function of(ConnectionListItem ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @param list<ConnectionListItem> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<ConnectionListItem>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
