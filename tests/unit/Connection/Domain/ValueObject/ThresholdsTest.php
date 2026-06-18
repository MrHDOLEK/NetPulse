<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidThresholds;
use App\Connection\Domain\ValueObject\Thresholds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThresholdsTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, ?float, ?float, ?float}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'download ratio zero' => [0.0, 0.7, 100.0, 50.0, 0.05];
        yield 'download ratio negative' => [-0.1, 0.7, 100.0, 50.0, 0.05];
        yield 'download ratio above one' => [1.1, 0.7, 100.0, 50.0, 0.05];
        yield 'upload ratio zero' => [0.7, 0.0, 100.0, 50.0, 0.05];
        yield 'upload ratio above one' => [0.7, 1.5, 100.0, 50.0, 0.05];
        yield 'negative ping cap' => [0.7, 0.7, -1.0, 50.0, 0.05];
        yield 'negative jitter cap' => [0.7, 0.7, 100.0, -0.1, 0.05];
        yield 'negative loss cap' => [0.7, 0.7, 100.0, 50.0, -0.01];
    }

    public function testDefaultCarriesTheApprovedValues(): void
    {
        $thresholds = Thresholds::default();

        self::assertSame(0.7, $thresholds->minDownloadRatio());
        self::assertSame(0.7, $thresholds->minUploadRatio());
        self::assertSame(100.0, $thresholds->maxPingMs());
        self::assertSame(50.0, $thresholds->maxJitterMs());
        self::assertSame(0.05, $thresholds->maxPacketLossRatio());
    }

    public function testOfAllowsNullCapsAndBoundaryRatios(): void
    {
        $thresholds = Thresholds::of(1.0, 0.5, null, null, null);

        self::assertSame(1.0, $thresholds->minDownloadRatio());
        self::assertSame(0.5, $thresholds->minUploadRatio());
        self::assertNull($thresholds->maxPingMs());
        self::assertNull($thresholds->maxJitterMs());
        self::assertNull($thresholds->maxPacketLossRatio());
    }

    public function testOfAllowsZeroLossCap(): void
    {
        $thresholds = Thresholds::of(0.7, 0.7, 100.0, 50.0, 0.0);

        self::assertSame(0.0, $thresholds->maxPacketLossRatio());
    }

    #[DataProvider('provideInvalid')]
    public function testRejectsInvalidValues(
        float $minDownloadRatio,
        float $minUploadRatio,
        ?float $maxPingMs,
        ?float $maxJitterMs,
        ?float $maxPacketLossRatio,
    ): void {
        $this->expectException(InvalidThresholds::class);

        Thresholds::of($minDownloadRatio, $minUploadRatio, $maxPingMs, $maxJitterMs, $maxPacketLossRatio);
    }
}
