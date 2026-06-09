<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\ValueObject\NotificationSettings;

interface NotificationSettingsProvider
{
    public function current(): NotificationSettings;
}
