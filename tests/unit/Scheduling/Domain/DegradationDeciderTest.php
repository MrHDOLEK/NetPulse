<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain;

use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Scheduling\Domain\DegradationDecider;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DegradationDeciderTest extends TestCase
{
    /**
     * @return iterable<string, array{HealthHistory, AdaptivePolicy, bool}>
     */
    public static function provideHistories(): iterable
    {
        $policy = AdaptivePolicy::of(300, 3, 4);

        yield 'empty history is not degraded' => [HealthHistory::empty(), $policy, false];

        yield 'newest healthy is not degraded' => [
            self::history(self::healthy(0), self::unhealthy(1)),
            $policy,
            false,
        ];

        yield 'newest unhealthy is degraded' => [
            self::history(self::unhealthy(0), self::healthy(1)),
            $policy,
            true,
        ];

        yield 'newest failed is degraded' => [
            self::history(self::failed(0), self::healthy(1)),
            $policy,
            true,
        ];

        yield 'newest healthy null verdict is not degraded' => [
            self::history(self::nullVerdict(0)),
            $policy,
            false,
        ];

        yield 'recovery: newest 3 healthy overrides older bad' => [
            self::history(self::healthy(0), self::healthy(1), self::healthy(2), self::failed(3), self::unhealthy(4)),
            $policy,
            false,
        ];

        yield 'two healthy then unhealthy newest is degraded' => [
            self::history(self::unhealthy(0), self::healthy(1), self::healthy(2)),
            $policy,
            true,
        ];

        yield 'backoff: newest 4 failed is not degraded' => [
            self::history(self::failed(0), self::failed(1), self::failed(2), self::failed(3), self::healthy(4)),
            $policy,
            false,
        ];

        yield 'below backoff: 3 failed still degraded' => [
            self::history(self::failed(0), self::failed(1), self::failed(2), self::healthy(3)),
            $policy,
            true,
        ];

        yield 'mixed bad newest run is degraded' => [
            self::history(self::failed(0), self::unhealthy(1), self::failed(2), self::failed(3)),
            $policy,
            true,
        ];
    }

    #[DataProvider('provideHistories')]
    public function testIsDegraded(HealthHistory $history, AdaptivePolicy $policy, bool $expected): void
    {
        self::assertSame($expected, new DegradationDecider()->isDegraded($history, $policy));
    }

    private static function history(HealthSample ...$samples): HealthHistory
    {
        return HealthHistory::of(...$samples);
    }

    private static function healthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), true);
    }

    private static function unhealthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), false);
    }

    private static function nullVerdict(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), null);
    }

    private static function failed(int $index): HealthSample
    {
        return HealthSample::failed(self::at($index));
    }

    private static function at(int $index): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-06 12:00:00')->modify('-' . ($index * 60) . ' seconds');
    }
}
