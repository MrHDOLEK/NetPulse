<?php

declare(strict_types=1);

namespace App\Connection\Domain\Enum;

enum ScheduleMode: string
{
    case Cron = "cron";
    case Even = "even";
}
