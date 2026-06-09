<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<ConnectionOverview>
 */
final class ConnectionOverviewCollection extends Collection
{
    public static function of(ConnectionOverview ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @param list<ConnectionOverview> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<ConnectionOverview>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
