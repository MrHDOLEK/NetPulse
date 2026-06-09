<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\ValueObject\Notification;

interface NotificationRenderer
{
    /**
     * @param array<string, scalar> $context
     */
    public function render(NotificationKind $kind, NotificationSeverity $severity, array $context): Notification;

    public function renderDigest(string $period, ConnectionDigestCollection $digests): Notification;
}
