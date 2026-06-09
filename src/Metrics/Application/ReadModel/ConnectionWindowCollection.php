<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Shared\Domain\Collection;

use function array_values;

/**
 * @extends Collection<ConnectionWindow>
 */
final class ConnectionWindowCollection extends Collection
{
    public static function of(ConnectionWindow ...$windows): self
    {
        return new self(array_values($windows));
    }

    /**
     * @param list<ConnectionWindow> $windows
     */
    public static function fromList(array $windows): self
    {
        return new self($windows);
    }

    /**
     * @return list<ConnectionWindow>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
