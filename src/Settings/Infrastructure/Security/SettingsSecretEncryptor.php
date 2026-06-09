<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Security;

use App\Settings\Application\SettingsException;
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

final readonly class SettingsSecretEncryptor
{
    private string $keyHex;

    public function __construct(
        #[Autowire("%env(string:SETTINGS_ENCRYPTION_KEY)%")]
        #[SensitiveParameter]
        string $settingsKeyHex,
        #[Autowire("%env(string:TOTP_ENCRYPTION_KEY)%")]
        #[SensitiveParameter]
        string $totpKeyHex,
    ) {
        $this->keyHex = $settingsKeyHex !== "" ? $settingsKeyHex : $totpKeyHex;
    }

    public function canEncrypt(): bool
    {
        return $this->validKey() !== null;
    }

    /**
     * @throws SettingsException when no usable key is configured
     */
    public function encrypt(#[SensitiveParameter] string $plain): string
    {
        $key = $this->validKey();

        if ($key === null) {
            throw SettingsException::encryptionUnavailable();
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_bin2hex($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public function decrypt(#[SensitiveParameter] string $hex): ?string
    {
        $key = $this->validKey();

        if ($key === null) {
            return null;
        }

        try {
            $raw = sodium_hex2bin($hex);
        } catch (SodiumException) {
            return null;
        }

        if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        return $plain === false ? null : $plain;
    }

    private function validKey(): ?string
    {
        if ($this->keyHex === "") {
            return null;
        }

        try {
            $key = sodium_hex2bin($this->keyHex);
        } catch (SodiumException) {
            return null;
        }

        return strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : null;
    }
}
