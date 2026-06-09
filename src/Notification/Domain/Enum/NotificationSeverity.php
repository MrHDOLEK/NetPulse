<?php

declare(strict_types=1);

namespace App\Notification\Domain\Enum;

enum NotificationSeverity: string
{
    case Info = "info";
    case Warning = "warning";
    case Critical = "critical";
}
