<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

final readonly class HeatmapCell
{
    public function __construct(
        public int $dow,
        public int $hour,
        public ?float $value,
        public int $samples,
        public int $attempts,
    ) {}

    public static function empty(int $dow, int $hour): self
    {
        return new self($dow, $hour, null, 0, 0);
    }
}
