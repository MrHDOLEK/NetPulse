<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<SeriesBucket>
 */
final class SeriesBucketCollection extends Collection
{
    /**
     * @param list<SeriesBucket> $items
     */
    private function __construct(
        array $items,
        private readonly ?float $trendPct,
    ) {
        parent::__construct($items);
    }

    public static function of(SeriesBucket ...$items): self
    {
        return new self(array_values($items), null);
    }

    /**
     * @param list<SeriesBucket> $items
     */
    public static function fromList(array $items): self
    {
        return new self($items, null);
    }

    /**
     * @param list<SeriesBucket> $items
     */
    public static function withTrend(array $items, ?float $trendPct): self
    {
        return new self($items, $trendPct);
    }

    public function trendPct(): ?float
    {
        return $this->trendPct;
    }

    /**
     * @return list<SeriesBucket>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
