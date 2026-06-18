<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\Format\UnitFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, string}>
     */
    public static function bitsPerSecondProvider(): iterable
    {
        yield 'two gigabit -> Gbps, one decimal' => [2_000_000_000, '2.0 Gbps'];
        yield 'exactly one gigabit -> 1.0 Gbps' => [1_000_000_000, '1.0 Gbps'];
        yield 'just under a gigabit stays Mbps integer' => [950_000_000, '950 Mbps'];
        yield 'sub-ten Mbps keeps one decimal' => [9_500_000, '9.5 Mbps'];
        yield 'hundreds of Mbps render as integer' => [120_000_000, '120 Mbps'];
        yield 'zero renders as 0 Mbps' => [0, '0 Mbps'];
        yield 'null renders as em dash' => [null, '—'];
    }

    /**
     * @return iterable<string, array{?float, string}>
     */
    public static function secondsProvider(): iterable
    {
        yield 'fifty ms whole -> integer' => [0.05, '50 ms'];
        yield 'twelve-point-three ms keeps decimal' => [0.0123, '12.3 ms'];
        yield 'two hundred ms whole -> integer' => [0.2, '200 ms'];
        yield 'sub-ten ms keeps decimal' => [0.0023, '2.3 ms'];
        yield 'null renders as em dash' => [null, '—'];
    }

    /**
     * @return iterable<string, array{?float, string}>
     */
    public static function ratioProvider(): iterable
    {
        yield 'one-point-two percent keeps decimal' => [0.012, '1.2 %'];
        yield 'twenty percent whole -> integer' => [0.2, '20 %'];
        yield 'zero percent' => [0.0, '0 %'];
        yield 'null renders as em dash' => [null, '—'];
    }

    #[DataProvider('bitsPerSecondProvider')]
    public function testBitsPerSecond(?int $bits, string $expected): void
    {
        self::assertSame($expected, UnitFormatter::bitsPerSecond($bits));
    }

    #[DataProvider('secondsProvider')]
    public function testSeconds(?float $seconds, string $expected): void
    {
        self::assertSame($expected, UnitFormatter::seconds($seconds));
    }

    #[DataProvider('ratioProvider')]
    public function testRatio(?float $ratio, string $expected): void
    {
        self::assertSame($expected, UnitFormatter::ratio($ratio));
    }
}
