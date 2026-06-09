<?php

declare(strict_types=1);

namespace App\Metrics\Application\RemoteWrite;

final readonly class PushMeasurementMessage
{
    public function __construct(
        public string $measurementId,
    ) {}
}
