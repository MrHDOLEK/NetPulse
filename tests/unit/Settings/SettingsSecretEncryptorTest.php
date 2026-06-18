<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Settings\Application\SettingsException;
use App\Settings\Infrastructure\Security\SettingsSecretEncryptor;
use PHPUnit\Framework\TestCase;

use function substr;

final class SettingsSecretEncryptorTest extends TestCase
{
    private const string KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';
    private const string OTHER_KEY_HEX = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100';

    public function testRoundTrips(): void
    {
        $encryptor = new SettingsSecretEncryptor(self::KEY_HEX, '');

        $cipher = $encryptor->encrypt('client-secret');

        self::assertSame('client-secret', $encryptor->decrypt($cipher));
    }

    public function testCiphertextIsNotThePlaintext(): void
    {
        $encryptor = new SettingsSecretEncryptor(self::KEY_HEX, '');

        $cipher = $encryptor->encrypt('client-secret');

        self::assertStringNotContainsString('client-secret', $cipher);
    }

    public function testTwoEncryptionsDiffer(): void
    {
        $encryptor = new SettingsSecretEncryptor(self::KEY_HEX, '');

        self::assertNotSame($encryptor->encrypt('x'), $encryptor->encrypt('x'));
    }

    public function testFallsBackToTotpKeyWhenSettingsKeyEmpty(): void
    {
        $encryptor = new SettingsSecretEncryptor('', self::KEY_HEX);

        self::assertTrue($encryptor->canEncrypt());
        $cipher = $encryptor->encrypt('secret');
        self::assertSame('secret', $encryptor->decrypt($cipher));
    }

    public function testDedicatedKeyTakesPrecedenceOverTotpKey(): void
    {
        $cipher = new SettingsSecretEncryptor(self::KEY_HEX, self::OTHER_KEY_HEX)->encrypt('secret');

        $withTotpOnly = new SettingsSecretEncryptor('', self::OTHER_KEY_HEX);
        self::assertNull($withTotpOnly->decrypt($cipher));
    }

    public function testNoKeyCannotEncryptAndThrows(): void
    {
        $encryptor = new SettingsSecretEncryptor('', '');

        self::assertFalse($encryptor->canEncrypt());
        $this->expectException(SettingsException::class);
        $encryptor->encrypt('secret');
    }

    public function testMalformedKeyCannotEncrypt(): void
    {
        $encryptor = new SettingsSecretEncryptor('not-hex', '');

        self::assertFalse($encryptor->canEncrypt());
    }

    public function testWrongLengthKeyCannotEncrypt(): void
    {
        $encryptor = new SettingsSecretEncryptor('000102030405060708090a0b0c0d0e0f', '');

        self::assertFalse($encryptor->canEncrypt());
    }

    public function testDecryptReturnsNullOnTamperedCiphertext(): void
    {
        $encryptor = new SettingsSecretEncryptor(self::KEY_HEX, '');
        $cipher = $encryptor->encrypt('secret');

        $tampered = substr($cipher, 0, -1) . ($cipher[-1] === '0' ? '1' : '0');

        self::assertNull($encryptor->decrypt($tampered));
    }

    public function testDecryptReturnsNullOnNonHex(): void
    {
        $encryptor = new SettingsSecretEncryptor(self::KEY_HEX, '');

        self::assertNull($encryptor->decrypt('zzzz-not-hex'));
    }

    public function testWrongKeyCannotDecrypt(): void
    {
        $cipher = new SettingsSecretEncryptor(self::KEY_HEX, '')->encrypt('secret');

        $other = new SettingsSecretEncryptor(self::OTHER_KEY_HEX, '');

        self::assertNull($other->decrypt($cipher));
    }
}
