<?php

declare(strict_types=1);

namespace App\Settings\Application;

use App\Settings\Domain\SettingKey;

interface SettingsWriter
{
    /**
     * @throws SettingsException when a secret value is supplied but the encryption key is unavailable
     */
    public function set(SettingKey $key, ?string $value): void;
}
