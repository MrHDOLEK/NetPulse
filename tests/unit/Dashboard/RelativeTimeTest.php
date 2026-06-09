<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\Format\RelativeTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelativeTimeTest extends TestCase
{
    private const int NOW = 1_700_000_000;

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function deltaProvider(): iterable
    {
        yield "now exactly -> just now" => [0, "just now"];
        yield "30s ago -> just now" => [30, "just now"];
        yield "59s ago -> just now (boundary below a minute)" => [59, "just now"];
        yield "60s ago -> 1 minute (singular)" => [60, "1 minute ago"];
        yield "120s ago -> 2 minutes" => [120, "2 minutes ago"];
        yield "3540s ago -> 59 minutes (boundary below an hour)" => [3540, "59 minutes ago"];
        yield "3600s ago -> 1 hour (singular)" => [3600, "1 hour ago"];
        yield "7200s ago -> 2 hours" => [7200, "2 hours ago"];
        yield "86340s ago -> 23 hours (boundary below a day)" => [86_340, "23 hours ago"];
        yield "86400s ago -> 1 day (singular)" => [86_400, "1 day ago"];
        yield "172800s ago -> 2 days" => [172_800, "2 days ago"];
    }

    #[DataProvider("deltaProvider")]
    public function testFromUnixRendersRelativeLabel(int $secondsAgo, string $expected): void
    {
        self::assertSame($expected, RelativeTime::fromUnix(self::NOW - $secondsAgo, self::NOW));
    }

    public function testFutureInstantClampsToJustNow(): void
    {
        self::assertSame("just now", RelativeTime::fromUnix(self::NOW + 300, self::NOW));
    }
}
