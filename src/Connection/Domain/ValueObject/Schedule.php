<?php

declare(strict_types=1);

namespace App\Connection\Domain\ValueObject;

use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\Exception\InvalidSchedule;

use function array_values;

final readonly class Schedule
{
    /**
     * @param list<string> $cronExpressions
     */
    private function __construct(
        private ScheduleMode $mode,
        private array $cronExpressions,
        private int $testsPerDay,
        private int $jitterSeconds,
    ) {}

    public static function cron(string ...$expressions): self
    {
        $expressions = array_values($expressions);

        if ($expressions === []) {
            throw InvalidSchedule::emptyCronExpressions();
        }

        return new self(ScheduleMode::Cron, $expressions, 0, 0);
    }

    public static function even(int $testsPerDay, int $jitterSeconds): self
    {
        if ($testsPerDay < 1) {
            throw InvalidSchedule::testsPerDayTooLow($testsPerDay);
        }

        if ($jitterSeconds < 0) {
            throw InvalidSchedule::negativeJitter($jitterSeconds);
        }

        return new self(ScheduleMode::Even, [], $testsPerDay, $jitterSeconds);
    }

    public function mode(): ScheduleMode
    {
        return $this->mode;
    }

    /**
     * @return list<string>
     */
    public function cronExpressions(): array
    {
        return $this->cronExpressions;
    }

    public function testsPerDay(): int
    {
        return $this->testsPerDay;
    }

    public function jitterSeconds(): int
    {
        return $this->jitterSeconds;
    }
}
