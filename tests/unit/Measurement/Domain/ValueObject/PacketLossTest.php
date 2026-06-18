<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\ValueObject;

use App\Measurement\Domain\Exception\InvalidPacketLoss;
use App\Measurement\Domain\ValueObject\PacketLoss;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PacketLossTest extends TestCase
{
    /**
     * @return iterable<string, array{float}>
     */
    public static function provideValidRatios(): iterable
    {
        yield 'lower boundary' => [0.0];
        yield 'mid range' => [0.25];
        yield 'upper boundary' => [1.0];
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideInvalidRatios(): iterable
    {
        yield 'above one' => [1.5];
        yield 'negative' => [-0.1];
    }

    #[DataProvider('provideValidRatios')]
    public function testStoresValidRatio(float $ratio): void
    {
        $this->assertSame($ratio, new PacketLoss($ratio)->ratio);
    }

    #[DataProvider('provideInvalidRatios')]
    public function testRejectsOutOfRangeRatio(float $ratio): void
    {
        $this->expectException(InvalidPacketLoss::class);

        new PacketLoss($ratio);
    }

    public function testFromOoklaPercentConvertsToRatio(): void
    {
        $packetLoss = PacketLoss::fromOoklaPercent(12.5);

        $this->assertSame(0.125, $packetLoss->ratio);
    }
}
