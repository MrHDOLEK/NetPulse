<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Infrastructure\Doctrine\DoctrineNotificationSendCounter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineNotificationSendCounterTest extends KernelTestCase
{
    private Connection $connection;
    private DoctrineNotificationSendCounter $counter;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM notification_send_counts');

        $this->counter = new DoctrineNotificationSendCounter($this->connection);
    }

    public function testFirstIncrementCreatesTheRowAndSubsequentOnesAccumulate(): void
    {
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'sent');
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'sent');
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'sent');

        self::assertSame(3, $this->total('alert', 'webhook', 'sent'));

        self::assertSame(1, $this->rowCount('alert', 'webhook', 'sent'));
    }

    public function testDistinctTriplesAreCountedIndependently(): void
    {
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'sent');
        $this->counter->increment(NotificationKind::Recovery, 'webhook', 'sent');
        $this->counter->increment(NotificationKind::Alert, 'email', 'sent');
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'failed');
        $this->counter->increment(NotificationKind::Alert, 'webhook', 'failed');
        $this->counter->increment(NotificationKind::Digest, 'webhook', 'skipped');

        self::assertSame(1, $this->total('alert', 'webhook', 'sent'));
        self::assertSame(1, $this->total('recovery', 'webhook', 'sent'));
        self::assertSame(1, $this->total('alert', 'email', 'sent'));
        self::assertSame(2, $this->total('alert', 'webhook', 'failed'));
        self::assertSame(1, $this->total('digest', 'webhook', 'skipped'));

        $rows = $this->connection->fetchOne('SELECT COUNT(*) FROM notification_send_counts');
        self::assertSame(5, (int) $rows);
    }

    public function testConcurrentSafeUpsertPathIsExercisedAcrossManyIncrements(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->counter->increment(NotificationKind::Alert, 'webhook', 'sent');
        }

        self::assertSame(50, $this->total('alert', 'webhook', 'sent'));
        self::assertSame(1, $this->rowCount('alert', 'webhook', 'sent'));
    }

    private function total(string $kind, string $channel, string $status): int
    {
        $value = $this->connection->fetchOne('SELECT total FROM notification_send_counts WHERE kind = :kind AND channel = :channel AND status = :status', [
            'kind' => $kind,
            'channel' => $channel,
            'status' => $status,
        ]);

        return $value === false ? 0 : (int) $value;
    }

    private function rowCount(string $kind, string $channel, string $status): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM notification_send_counts WHERE kind = :kind AND channel = :channel AND status = :status', [
            'kind' => $kind,
            'channel' => $channel,
            'status' => $status,
        ]);

        return (int) $value;
    }
}
