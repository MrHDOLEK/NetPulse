<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Labels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LabelsTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, string, ?string}>
     */
    public static function provideGetCases(): iterable
    {
        yield 'present key' => [['site' => 'home'], 'site', 'home'];
        yield 'missing key' => [['site' => 'home'], 'link', null];
        yield 'empty map' => [[], 'site', null];
        yield 'empty string value' => [['site' => ''], 'site', ''];
    }

    /**
     * @return iterable<string, array{array<string, string>, string, bool}>
     */
    public static function provideHasCases(): iterable
    {
        yield 'present key' => [['site' => 'home'], 'site', true];
        yield 'missing key' => [['site' => 'home'], 'link', false];
        yield 'empty string value still present' => [['site' => ''], 'site', true];
        yield 'empty map' => [[], 'site', false];
    }

    public function testEmptyHasNoEntries(): void
    {
        $labels = Labels::empty();

        self::assertTrue($labels->isEmpty());
        self::assertSame([], $labels->all());
        self::assertNull($labels->get('site'));
        self::assertFalse($labels->has('site'));
    }

    public function testFromArrayPreservesKeysAndValues(): void
    {
        $labels = Labels::fromArray(['site' => 'home', 'link' => 'wan1']);

        self::assertFalse($labels->isEmpty());
        self::assertSame(['site' => 'home', 'link' => 'wan1'], $labels->all());
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('provideGetCases')]
    public function testGetReturnsValueOrNull(array $input, string $key, ?string $expected): void
    {
        self::assertSame($expected, Labels::fromArray($input)->get($key));
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('provideHasCases')]
    public function testHasReportsPresence(array $input, string $key, bool $expected): void
    {
        self::assertSame($expected, Labels::fromArray($input)->has($key));
    }
}
