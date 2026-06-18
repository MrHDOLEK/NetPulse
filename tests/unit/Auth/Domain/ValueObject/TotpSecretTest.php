<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\ValueObject;

use App\Auth\Domain\ValueObject\TotpSecret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TotpSecretTest extends TestCase
{
    public function testHoldsAndReturnsValue(): void
    {
        $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

        self::assertSame('JBSWY3DPEHPK3PXP', $secret->value());
        self::assertSame('JBSWY3DPEHPK3PXP', (string) $secret);
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TotpSecret('');
    }

    public function testEqualsByValue(): void
    {
        self::assertTrue(new TotpSecret('ABC123')->equals(new TotpSecret('ABC123')));
        self::assertFalse(new TotpSecret('ABC123')->equals(new TotpSecret('XYZ789')));
    }
}
