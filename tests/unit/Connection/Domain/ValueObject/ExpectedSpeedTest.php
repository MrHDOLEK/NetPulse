<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidExpectedSpeed;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpectedSpeedTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideValidSpeeds(): iterable
    {
        yield "typical plan" => [100_000_000, 20_000_000];
        yield "zero means unknown plan" => [0, 0];
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideNegativeSpeeds(): iterable
    {
        yield "negative download" => [-1, 20_000_000];
        yield "negative upload" => [100_000_000, -5];
    }

    #[DataProvider("provideValidSpeeds")]
    public function testHoldsDownloadAndUploadBits(int $download, int $upload): void
    {
        $expected = new ExpectedSpeed($download, $upload);

        $this->assertSame($download, $expected->expectedDownloadBits);
        $this->assertSame($upload, $expected->expectedUploadBits);
    }

    #[DataProvider("provideNegativeSpeeds")]
    public function testRejectsNegativeBits(int $download, int $upload): void
    {
        $this->expectException(InvalidExpectedSpeed::class);

        new ExpectedSpeed($download, $upload);
    }
}
