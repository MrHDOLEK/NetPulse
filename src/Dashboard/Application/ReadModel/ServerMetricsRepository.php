<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;

interface ServerMetricsRepository
{
    public function all(HeatmapWindow $window): ServerMetricsRowCollection;
}
