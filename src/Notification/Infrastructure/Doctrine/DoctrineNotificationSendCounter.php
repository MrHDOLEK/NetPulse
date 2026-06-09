<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\NotificationSendCounter;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: NotificationSendCounter::class)]
final readonly class DoctrineNotificationSendCounter implements NotificationSendCounter
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function increment(NotificationKind $kind, string $channel, string $status): void
    {
        $this->connection->executeStatement(
            "INSERT INTO notification_send_counts (kind, channel, status, total) "
            . "VALUES (:kind, :channel, :status, 1) "
            . "ON CONFLICT (kind, channel, status) DO UPDATE SET total = total + 1",
            [
                "kind" => $kind->value,
                "channel" => $channel,
                "status" => $status,
            ],
        );
    }
}
