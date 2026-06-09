<?php

declare(strict_types=1);

namespace App\Metrics\Application;

use App\Metrics\Application\ReadModel\DegradedRowCollection;
use App\Metrics\Application\ReadModel\ExpectedRowCollection;
use App\Metrics\Application\ReadModel\LatestMeasurementRowCollection;
use App\Metrics\Application\ReadModel\NotificationSendRowCollection;
use App\Metrics\Application\ReadModel\RunCountRowCollection;
use App\Metrics\Application\ReadModel\UnhealthyCountRowCollection;

interface MetricsRepository
{
    public function latestPerConnection(): LatestMeasurementRowCollection;

    public function runCounts(): RunCountRowCollection;

    public function connectionsExpected(): ExpectedRowCollection;

    public function unhealthyCounts(): UnhealthyCountRowCollection;

    public function connectionDegraded(): DegradedRowCollection;

    public function remoteWriteFailures(): int;

    public function notificationSends(): NotificationSendRowCollection;
}
