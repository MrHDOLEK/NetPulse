<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use DateTimeImmutable;

class AppSetting
{
    public function __construct(
        private readonly SettingKey $key,
        private string $value,
        private bool $isEncrypted,
        private DateTimeImmutable $updatedAt,
    ) {}

    public function key(): SettingKey
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeValue(string $value, bool $isEncrypted, DateTimeImmutable $updatedAt): void
    {
        $this->value = $value;
        $this->isEncrypted = $isEncrypted;
        $this->updatedAt = $updatedAt;
    }
}
