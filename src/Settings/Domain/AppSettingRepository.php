<?php

declare(strict_types=1);

namespace App\Settings\Domain;

interface AppSettingRepository
{
    public function find(SettingKey $key): ?AppSetting;

    public function save(AppSetting $setting): void;

    /**
     * @return array<string, AppSetting>
     */
    public function all(): array;
}
