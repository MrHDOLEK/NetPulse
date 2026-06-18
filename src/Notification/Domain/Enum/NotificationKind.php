<?php

declare(strict_types=1);

namespace App\Notification\Domain\Enum;

enum NotificationKind: string
{
    case Alert = 'alert';
    case Recovery = 'recovery';
    case Digest = 'digest';
}
