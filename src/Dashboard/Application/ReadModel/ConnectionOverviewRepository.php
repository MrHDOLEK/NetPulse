<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Dashboard\Application\ReadModel\Enum\SeriesRange;

interface ConnectionOverviewRepository
{
    public function overview(SeriesRange $range): ConnectionOverviewCollection;
}
