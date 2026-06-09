<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;

interface ConnectionSeriesRepository
{
    public function series(ConnectionId $id, SeriesRange $range, SeriesMetric $metric): SeriesBucketCollection;
}
