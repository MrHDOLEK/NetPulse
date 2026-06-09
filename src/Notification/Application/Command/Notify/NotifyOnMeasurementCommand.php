<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\Notify;

final readonly class NotifyOnMeasurementCommand
{
    public function __construct(
        public string $measurementId,
    ) {}
}
