<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\ValueObject;

use App\Metrics\Domain\RemoteWrite\Collection\LabelCollection;
use App\Metrics\Domain\RemoteWrite\Collection\SampleCollection;
use App\Metrics\Domain\RemoteWrite\Exception\InvalidTimeSeries;

final readonly class TimeSeries
{
    public function __construct(
        public LabelCollection $labels,
        public SampleCollection $samples,
    ) {
        foreach ($labels as $label) {
            if ($label->name === "__name__") {
                return;
            }
        }

        throw InvalidTimeSeries::missingNameLabel();
    }
}
