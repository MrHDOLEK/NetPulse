<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Notification\Domain\ValueObject\Notification;

interface NotificationDispatcher
{
    public function send(Notification $notification): void;
}
