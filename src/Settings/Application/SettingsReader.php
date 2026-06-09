<?php

declare(strict_types=1);

namespace App\Settings\Application;

use App\Settings\Domain\SettingKey;

interface SettingsReader
{
    public function get(SettingKey $key): ?string;

    public function getString(SettingKey $key): string;

    public function getBool(SettingKey $key): bool;
}
