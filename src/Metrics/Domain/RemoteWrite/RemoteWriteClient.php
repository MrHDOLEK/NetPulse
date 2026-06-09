<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\Exception\RemoteWriteFailed;

interface RemoteWriteClient
{
    /**
     * @throws RemoteWriteFailed
     */
    public function write(TimeSeriesCollection $series): void;
}
