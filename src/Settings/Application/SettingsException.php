<?php

declare(strict_types=1);

namespace App\Settings\Application;

use RuntimeException;

final class SettingsException extends RuntimeException
{
    public static function encryptionUnavailable(): self
    {
        return new self(
            'Cannot save the SSO client secret: the encryption key is not configured. '
            . 'Set TOTP_ENCRYPTION_KEY (a 64-character hex string) and try again.',
        );
    }
}
