<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class NotificationSendRow
{
    public function __construct(
        public string $kind,
        public string $channel,
        public string $status,
        public int $total,
    ) {}
}
