<?php

declare(strict_types=1);

namespace App\Settings\Application\SaveSettings;

use App\Settings\Domain\SettingKey;

/**
 * @phpstan-type SettingValues array<value-of<SettingKey>, string|null>
 */
final readonly class SaveSettingsCommand
{
    /**
     * @param array<string, string|null> $values map of SettingKey->value (null only for a secret kept as-is)
     */
    public function __construct(
        public array $values,
    ) {}
}
