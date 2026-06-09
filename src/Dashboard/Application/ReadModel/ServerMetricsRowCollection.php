<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<ServerMetricsRow>
 */
final class ServerMetricsRowCollection extends Collection
{
    public static function of(ServerMetricsRow ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @param list<ServerMetricsRow> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<ServerMetricsRow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
