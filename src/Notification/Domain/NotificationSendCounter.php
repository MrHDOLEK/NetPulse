<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Notification\Domain\Enum\NotificationKind;

interface NotificationSendCounter
{
    public function increment(NotificationKind $kind, string $channel, string $status): void;
}
