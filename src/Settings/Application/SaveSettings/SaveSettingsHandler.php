<?php

declare(strict_types=1);

namespace App\Settings\Application\SaveSettings;

use App\Settings\Application\SettingsWriter;
use App\Settings\Domain\SettingKey;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SaveSettingsHandler
{
    public function __construct(
        private SettingsWriter $writer,
    ) {}

    public function __invoke(SaveSettingsCommand $command): void
    {
        foreach ($command->values as $keyValue => $value) {
            $key = SettingKey::tryFrom($keyValue);

            if ($key === null) {
                continue;
            }

            $this->writer->set($key, $value);
        }
    }
}
