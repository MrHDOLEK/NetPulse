<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Collection;

use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Shared\Domain\Collection;

/**
 * @extends Collection<Sample>
 */
final class SampleCollection extends Collection
{
    public static function of(Sample ...$samples): self
    {
        return new self(array_values($samples));
    }

    /**
     * @param list<Sample> $samples
     */
    public static function fromList(array $samples): self
    {
        return new self($samples);
    }

    /**
     * @return list<Sample>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
