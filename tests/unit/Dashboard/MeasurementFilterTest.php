<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Measurement\Domain\Enum\MeasurementStatus;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeasurementFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function lastDaysWindowProvider(): iterable
    {
        yield 'one day' => [1, '2026-06-06 12:00:00'];
        yield 'seven days' => [7, '2026-05-31 12:00:00'];
        yield 'thirty days' => [30, '2026-05-08 12:00:00'];
        yield 'ninety days' => [90, '2026-03-09 12:00:00'];
    }

    public function testRejectsSinceAfterUntil(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MeasurementFilter(
            connection: null,
            since: new DateTimeImmutable('2026-06-07 12:00:01', new DateTimeZone('UTC')),
            until: new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC')),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );
    }

    public function testAcceptsSinceBeforeUntil(): void
    {
        $since = new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC'));
        $until = new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC'));

        $filter = new MeasurementFilter(
            connection: null,
            since: $since,
            until: $until,
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        self::assertSame($since, $filter->since);
        self::assertSame($until, $filter->until);
    }

    public function testAcceptsSinceEqualToUntil(): void
    {
        $instant = new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC'));

        $filter = new MeasurementFilter(
            connection: null,
            since: $instant,
            until: $instant,
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        self::assertSame($instant, $filter->since);
        self::assertSame($instant, $filter->until);
    }

    public function testLastDaysBuildsWindowFromNow(): void
    {
        $now = new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC'));

        $filter = MeasurementFilter::lastDays(
            days: 7,
            now: $now,
            connection: null,
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        self::assertSame($now, $filter->until);
        self::assertSame('2026-05-31 12:00:00', $filter->since->format('Y-m-d H:i:s'));
    }

    public function testLastDaysPassesThroughAllCriteria(): void
    {
        $now = new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC'));
        $connection = new ConnectionId('0191E5C2-7A3B-7E4A-9C1D-2F6B8A0E4D11');

        $filter = MeasurementFilter::lastDays(
            days: 30,
            now: $now,
            connection: $connection,
            serverId: 'server-42',
            status: MeasurementStatus::Failed,
            healthy: false,
            scheduled: true,
        );

        self::assertSame($connection, $filter->connection);
        self::assertSame('server-42', $filter->serverId);
        self::assertSame(MeasurementStatus::Failed, $filter->status);
        self::assertFalse($filter->healthy);
        self::assertTrue($filter->scheduled);
        self::assertSame('2026-05-08 12:00:00', $filter->since->format('Y-m-d H:i:s'));
    }

    #[DataProvider('lastDaysWindowProvider')]
    public function testLastDaysWindowBounds(int $days, string $expectedSince): void
    {
        $now = new DateTimeImmutable('2026-06-07 12:00:00', new DateTimeZone('UTC'));

        $filter = MeasurementFilter::lastDays(
            days: $days,
            now: $now,
            connection: null,
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );

        self::assertSame($expectedSince, $filter->since->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-07 12:00:00', $filter->until->format('Y-m-d H:i:s'));
    }
}
