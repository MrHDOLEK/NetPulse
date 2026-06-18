<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Auth\Infrastructure\Security\TotpSecretEncryptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function random_bytes;
use function substr;

final class TotpSecretEncryptorTest extends TestCase
{
    private const string KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testRoundTrips(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);

        $cipher = $encryptor->encrypt('JBSWY3DPEHPK3PXP');

        self::assertSame('JBSWY3DPEHPK3PXP', $encryptor->decrypt($cipher));
    }

    public function testTwoEncryptionsOfSamePlaintextDiffer(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);

        self::assertNotSame($encryptor->encrypt('SECRET'), $encryptor->encrypt('SECRET'));
    }

    public function testCiphertextIsNotThePlaintext(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);

        $cipher = $encryptor->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $cipher);
    }

    public function testTamperedCiphertextThrows(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);
        $cipher = $encryptor->encrypt('JBSWY3DPEHPK3PXP');

        $tampered = substr($cipher, 0, -1) . ($cipher[-1] === '0' ? '1' : '0');

        $this->expectException(RuntimeException::class);
        $encryptor->decrypt($tampered);
    }

    public function testNonHexCiphertextThrows(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);

        $this->expectException(RuntimeException::class);
        $encryptor->decrypt('zzzz-not-hex');
    }

    public function testTooShortCiphertextThrows(): void
    {
        $encryptor = new TotpSecretEncryptor(self::KEY_HEX);

        $this->expectException(RuntimeException::class);
        $encryptor->decrypt('0011');
    }

    public function testWrongKeyCannotDecrypt(): void
    {
        $cipher = new TotpSecretEncryptor(self::KEY_HEX)->encrypt('JBSWY3DPEHPK3PXP');

        $other = new TotpSecretEncryptor(bin2hex(random_bytes(32)));

        $this->expectException(RuntimeException::class);
        $other->decrypt($cipher);
    }

    public function testEmptyKeyConstructsButFailsOnUse(): void
    {
        $encryptor = new TotpSecretEncryptor('');

        $this->expectException(RuntimeException::class);
        $encryptor->encrypt('SECRET');
    }

    public function testMalformedKeyThrowsOnUse(): void
    {
        $encryptor = new TotpSecretEncryptor('not-hex');

        $this->expectException(RuntimeException::class);
        $encryptor->encrypt('SECRET');
    }

    public function testWrongLengthKeyThrowsOnUse(): void
    {
        $encryptor = new TotpSecretEncryptor('000102030405060708090a0b0c0d0e0f');

        $this->expectException(RuntimeException::class);
        $encryptor->encrypt('SECRET');
    }
}
