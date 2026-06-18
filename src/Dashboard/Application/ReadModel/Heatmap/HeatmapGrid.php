<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<HeatmapCell>
 */
final class HeatmapGrid extends Collection
{
    public static function key(int $dow, int $hour): string
    {
        return $dow . ':' . $hour;
    }

    /**
     * @param array<string, HeatmapCell> $populated keyed by self::key()
     */
    public static function fill(array $populated): self
    {
        $cells = [];

        for ($dow = 0; $dow < 7; ++$dow) {
            for ($hour = 0; $hour < 24; ++$hour) {
                $cells[] = $populated[self::key($dow, $hour)] ?? HeatmapCell::empty($dow, $hour);
            }
        }

        return new self($cells);
    }

    /**
     * @return list<HeatmapCell>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
