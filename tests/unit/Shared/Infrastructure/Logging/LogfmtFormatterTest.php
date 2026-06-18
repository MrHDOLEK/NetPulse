<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\LogfmtFormatter;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogfmtFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{Level, string, string, array<string, mixed>, string}>
     */
    public static function records(): iterable
    {
        yield 'simple message, no context' => [
            Level::Info,
            'app',
            'tick ran',
            [],
            "level=info msg=\"tick ran\" channel=app\n",
        ];

        yield 'single-word message is not quoted' => [
            Level::Debug,
            'app',
            'started',
            [],
            "level=debug msg=started channel=app\n",
        ];

        yield 'level is lowercased to its PSR name' => [
            Level::Warning,
            'app',
            'task failed',
            [],
            "level=warning msg=\"task failed\" channel=app\n",
        ];

        yield 'scalar context is inlined' => [
            Level::Info,
            'app',
            'due work fetched',
            ['probe' => 'home', 'tasks' => 3],
            "level=info msg=\"due work fetched\" channel=app probe=home tasks=3\n",
        ];

        yield 'context value with spaces is quoted' => [
            Level::Error,
            'app',
            'remote write push failed',
            ['error' => 'connection refused'],
            "level=error msg=\"remote write push failed\" channel=app error=\"connection refused\"\n",
        ];

        yield 'context value with an equals sign and quote is quoted and escaped' => [
            Level::Warning,
            'app',
            'boom',
            ['raw' => 'a="b"'],
            "level=warning msg=boom channel=app raw=\"a=\\\"b\\\"\"\n",
        ];

        yield 'bool and null context are rendered as keywords' => [
            Level::Info,
            'app',
            'x',
            ['ok' => true, 'missing' => null],
            "level=info msg=x channel=app ok=true missing=null\n",
        ];

        yield 'non-scalar context is json-encoded and quoted' => [
            Level::Info,
            'app',
            'x',
            ['labels' => ['a' => 1]],
            "level=info msg=x channel=app labels=\"{\\\"a\\\":1}\"\n",
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('records')]
    public function testRendersLogfmtLine(
        Level $level,
        string $channel,
        string $message,
        array $context,
        string $expected,
    ): void {
        $formatter = new LogfmtFormatter();

        $line = $formatter->format($this->record($level, $channel, $message, $context));

        self::assertSame($expected, $line);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(Level $level, string $channel, string $message, array $context): LogRecord
    {
        return new LogRecord(new DateTimeImmutable('2026-06-06T10:00:00+00:00'), $channel, $level, $message, $context);
    }
}
