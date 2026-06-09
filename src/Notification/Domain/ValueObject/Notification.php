<?php

declare(strict_types=1);

namespace App\Notification\Domain\ValueObject;

use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;

final readonly class Notification
{
    /**
     * @param array<string, scalar> $context
     */
    public function __construct(
        public NotificationKind $kind,
        public NotificationSeverity $severity,
        public string $subject,
        public string $body,
        public array $context,
    ) {}
}
