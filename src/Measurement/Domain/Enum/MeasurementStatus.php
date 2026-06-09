<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Enum;

enum MeasurementStatus: string
{
    case Completed = "completed";
    case Failed = "failed";
}
