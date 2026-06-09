<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

final readonly class HeatmapSample
{
    public function __construct(
        public int $completedAtUnix,
        public ?float $value,
        public ?bool $healthy,
    ) {}
}
