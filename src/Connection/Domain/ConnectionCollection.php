<?php

declare(strict_types=1);

namespace App\Connection\Domain;

use App\Connection\Domain\Entity\Connection;
use App\Shared\Domain\Collection;

/**
 * @extends Collection<Connection>
 */
final class ConnectionCollection extends Collection
{
    public static function of(Connection ...$connections): self
    {
        return new self(array_values($connections));
    }

    /**
     * @param list<Connection> $connections
     */
    public static function fromList(array $connections): self
    {
        return new self($connections);
    }

    /**
     * @return list<Connection>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
