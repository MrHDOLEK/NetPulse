<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function random_bytes;
use function sodium_bin2hex;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function sodium_hex2bin;
use function strlen;
use function substr;

use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

final readonly class TotpSecretEncryptor
{
    public function __construct(
        #[Autowire("%env(string:TOTP_ENCRYPTION_KEY)%")]
        #[SensitiveParameter]
        private string $keyHex,
    ) {}

    public function encrypt(#[SensitiveParameter] string $plain): string
    {
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_bin2hex($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public function decrypt(#[SensitiveParameter] string $hex): string
    {
        $key = $this->key();

        try {
            $raw = sodium_hex2bin($hex);
        } catch (SodiumException) {
            throw new RuntimeException("TOTP secret decryption failed.");
        }

        if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException("TOTP secret decryption failed.");
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        if ($plain === false) {
            throw new RuntimeException("TOTP secret decryption failed.");
        }

        return $plain;
    }

    private function key(): string
    {
        if ($this->keyHex === "") {
            throw new RuntimeException(
                "TOTP_ENCRYPTION_KEY is not set. Generate one with: php -r \"echo bin2hex(random_bytes(32));\".",
            );
        }

        try {
            $key = sodium_hex2bin($this->keyHex);
        } catch (SodiumException) {
            throw new RuntimeException("TOTP_ENCRYPTION_KEY must be a 64-character hex string (32 bytes).");
        }

        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException("TOTP_ENCRYPTION_KEY must be a 64-character hex string (32 bytes).");
        }

        return $key;
    }
}
