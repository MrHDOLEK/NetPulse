<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Collection;

use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Shared\Domain\Collection;

/**
 * @extends Collection<Label>
 */
final class LabelCollection extends Collection
{
    public static function of(Label ...$labels): self
    {
        return new self(array_values($labels));
    }

    /**
     * @param list<Label> $labels
     */
    public static function fromList(array $labels): self
    {
        return new self($labels);
    }

    /**
     * @return list<Label>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
