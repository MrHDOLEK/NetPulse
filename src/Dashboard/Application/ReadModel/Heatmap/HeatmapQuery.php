<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Heatmap;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;

final readonly class HeatmapQuery
{
    public function __construct(
        public HeatmapMetric $metric,
        public HeatmapWindow $window,
        public ConnectionId $connectionId,
    ) {}
}
