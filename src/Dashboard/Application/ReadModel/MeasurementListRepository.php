<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;

interface MeasurementListRepository
{
    public function list(
        MeasurementFilter $filter,
        int $limit,
        int $offset,
        MeasurementSort $sort,
    ): MeasurementListItemCollection;

    public function countMatching(MeasurementFilter $filter): int;
}
