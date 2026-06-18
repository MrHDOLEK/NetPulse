<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidAdaptivePolicy;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdaptivePolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'zero interval' => [0, 3, 5];
        yield 'negative interval' => [-1, 3, 5];
        yield 'zero recovery count' => [300, 0, 5];
        yield 'zero max failures' => [300, 3, 0];
    }

    public function testDefaultCarriesTheApprovedValues(): void
    {
        $policy = AdaptivePolicy::default();

        self::assertSame(300, $policy->adaptiveIntervalSeconds());
        self::assertSame(3, $policy->recoveryHealthyCount());
        self::assertSame(5, $policy->maxConsecutiveFailures());
    }

    public function testOfAllowsTheMinimumOfEachField(): void
    {
        $policy = AdaptivePolicy::of(1, 1, 1);

        self::assertSame(1, $policy->adaptiveIntervalSeconds());
        self::assertSame(1, $policy->recoveryHealthyCount());
        self::assertSame(1, $policy->maxConsecutiveFailures());
    }

    #[DataProvider('provideInvalid')]
    public function testRejectsInvalidValues(
        int $adaptiveIntervalSeconds,
        int $recoveryHealthyCount,
        int $maxConsecutiveFailures,
    ): void {
        $this->expectException(InvalidAdaptivePolicy::class);

        AdaptivePolicy::of($adaptiveIntervalSeconds, $recoveryHealthyCount, $maxConsecutiveFailures);
    }
}
