<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Collection;

use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use App\Shared\Domain\Collection;

/**
 * @extends Collection<TimeSeries>
 */
final class TimeSeriesCollection extends Collection
{
    public static function of(TimeSeries ...$series): self
    {
        return new self(array_values($series));
    }

    /**
     * @param list<TimeSeries> $series
     */
    public static function fromList(array $series): self
    {
        return new self($series);
    }

    /**
     * @return list<TimeSeries>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
