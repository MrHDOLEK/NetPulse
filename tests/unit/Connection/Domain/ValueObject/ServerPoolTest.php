<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\ValueObject\ServerPool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class ServerPoolTest extends TestCase
{
    /**
     * @return iterable<string, array{array<int, string>, list<string>}>
     */
    public static function provideArrays(): iterable
    {
        yield "empty" => [[], []];
        yield "already a list" => [["a", "b"], ["a", "b"]];
        yield "gapped keys reindexed" => [[3 => "a", 7 => "b"], ["a", "b"]];
        yield "single" => [[42 => "only"], ["only"]];
    }

    public function testEmptyHasNoServers(): void
    {
        $pool = ServerPool::empty();

        self::assertTrue($pool->isEmpty());
        self::assertSame([], $pool->all());
        self::assertCount(0, $pool);
    }

    public function testFromListKeepsOrder(): void
    {
        $pool = ServerPool::fromList("12345", "23456", "34567");

        self::assertFalse($pool->isEmpty());
        self::assertSame(["12345", "23456", "34567"], $pool->all());
        self::assertSame(["12345", "23456", "34567"], iterator_to_array($pool));
        self::assertCount(3, $pool);
    }

    /**
     * @param array<int, string> $input
     * @param list<string> $expected
     */
    #[DataProvider("provideArrays")]
    public function testFromArrayReindexesToAList(array $input, array $expected): void
    {
        self::assertSame($expected, ServerPool::fromArray($input)->all());
    }
}
