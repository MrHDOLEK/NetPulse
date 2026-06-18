<?php

declare(strict_types=1);

namespace App\Connection\Domain\Exception;

use App\Shared\Domain\DomainException;

final class InvalidSchedule extends DomainException
{
    public static function emptyCronExpressions(): self
    {
        return new self('A cron schedule requires at least one cron expression.');
    }

    public static function testsPerDayTooLow(int $testsPerDay): self
    {
        return new self('An even schedule requires testsPerDay >= 1, got ' . $testsPerDay . '.');
    }

    public static function negativeJitter(int $jitterSeconds): self
    {
        return new self('An even schedule requires jitterSeconds >= 0, got ' . $jitterSeconds . '.');
    }
}
