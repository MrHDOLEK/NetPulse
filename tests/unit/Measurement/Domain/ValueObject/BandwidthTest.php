<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\ValueObject;

use App\Measurement\Domain\ValueObject\Bandwidth;
use PHPUnit\Framework\TestCase;

final class BandwidthTest extends TestCase
{
    public function testExposesAllFields(): void
    {
        $bandwidth = new Bandwidth(
            downloadBits: 943_000_000,
            uploadBits: 187_000_000,
            downloadBytes: 1_200_000_000,
            uploadBytes: 240_000_000,
        );

        $this->assertSame(943_000_000, $bandwidth->downloadBits);
        $this->assertSame(187_000_000, $bandwidth->uploadBits);
        $this->assertSame(1_200_000_000, $bandwidth->downloadBytes);
        $this->assertSame(240_000_000, $bandwidth->uploadBytes);
    }
}
