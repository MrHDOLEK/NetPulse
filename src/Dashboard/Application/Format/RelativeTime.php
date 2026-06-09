<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Format;

final class RelativeTime
{
    private const int SECONDS_PER_MINUTE = 60;
    private const int SECONDS_PER_HOUR = 3_600;
    private const int SECONDS_PER_DAY = 86_400;

    public static function fromUnix(int $thenUnix, int $nowUnix): string
    {
        $delta = max(0, $nowUnix - $thenUnix);

        if ($delta < self::SECONDS_PER_MINUTE) {
            return "just now";
        }

        if ($delta < self::SECONDS_PER_HOUR) {
            return self::plural(intdiv($delta, self::SECONDS_PER_MINUTE), "minute");
        }

        if ($delta < self::SECONDS_PER_DAY) {
            return self::plural(intdiv($delta, self::SECONDS_PER_HOUR), "hour");
        }

        return self::plural(intdiv($delta, self::SECONDS_PER_DAY), "day");
    }

    private static function plural(int $count, string $unit): string
    {
        return $count . " " . $unit . ($count === 1 ? "" : "s") . " ago";
    }
}
