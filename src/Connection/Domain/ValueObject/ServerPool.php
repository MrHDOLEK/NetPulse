<?php

declare(strict_types=1);

namespace App\Connection\Domain\ValueObject;

use App\Shared\Domain\Collection;

use function array_values;

/**
 * @extends Collection<string>
 */
final class ServerPool extends Collection
{
    public static function fromList(string ...$servers): self
    {
        return new self(array_values($servers));
    }

    /**
     * @param array<int, string> $servers
     */
    public static function fromArray(array $servers): self
    {
        return new self(array_values($servers));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
