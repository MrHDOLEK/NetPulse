<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\Exception\InvalidSchedule;
use App\Connection\Domain\ValueObject\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScheduleTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideInvalidEven(): iterable
    {
        yield 'zero tests per day' => [0, 120];
        yield 'negative tests per day' => [-1, 120];
        yield 'negative jitter' => [24, -1];
    }

    public function testCronKeepsExpressionsInOrder(): void
    {
        $schedule = Schedule::cron('*/30 * * * *', '0 9 * * 1');

        self::assertSame(ScheduleMode::Cron, $schedule->mode());
        self::assertSame(['*/30 * * * *', '0 9 * * 1'], $schedule->cronExpressions());
    }

    public function testEvenCarriesTestsPerDayAndJitter(): void
    {
        $schedule = Schedule::even(48, 120);

        self::assertSame(ScheduleMode::Even, $schedule->mode());
        self::assertSame(48, $schedule->testsPerDay());
        self::assertSame(120, $schedule->jitterSeconds());
        self::assertSame([], $schedule->cronExpressions());
    }

    public function testEvenAllowsZeroJitter(): void
    {
        $schedule = Schedule::even(1, 0);

        self::assertSame(1, $schedule->testsPerDay());
        self::assertSame(0, $schedule->jitterSeconds());
    }

    public function testCronRejectsEmptyExpressionList(): void
    {
        $this->expectException(InvalidSchedule::class);

        Schedule::cron();
    }

    #[DataProvider('provideInvalidEven')]
    public function testEvenRejectsInvalidValues(int $testsPerDay, int $jitterSeconds): void
    {
        $this->expectException(InvalidSchedule::class);

        Schedule::even($testsPerDay, $jitterSeconds);
    }
}
