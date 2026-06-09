<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Scheduling\Domain\ValueObject\MarkedConnection;
use App\Shared\Domain\Collection;

use function array_values;

/**
 * @extends Collection<MarkedConnection>
 */
final class MarkedConnectionCollection extends Collection
{
    public static function of(MarkedConnection ...$marked): self
    {
        return new self(array_values($marked));
    }

    /**
     * @param list<MarkedConnection> $marked
     */
    public static function fromList(array $marked): self
    {
        return new self($marked);
    }

    /**
     * @return list<MarkedConnection>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
