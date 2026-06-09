<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;

interface HeatmapRepository
{
    public function grid(HeatmapQuery $query): HeatmapGrid;
}
