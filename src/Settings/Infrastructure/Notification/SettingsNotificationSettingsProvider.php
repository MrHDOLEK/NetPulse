<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Notification;

use App\Notification\Application\NotificationSettingsProvider;
use App\Notification\Domain\ValueObject\NotificationSettings;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_filter;
use function array_map;
use function array_values;
use function ctype_digit;
use function explode;
use function max;
use function trim;

#[AsAlias(id: NotificationSettingsProvider::class)]
final readonly class SettingsNotificationSettingsProvider implements NotificationSettingsProvider
{
    private const int DEFAULT_THRESHOLD = 3;
    private const int MIN_THRESHOLD = 1;

    public function __construct(
        private SettingsReader $settings,
    ) {}

    public function current(): NotificationSettings
    {
        return new NotificationSettings(
            enabled: $this->settings->getBool(SettingKey::NotifyEnabled),
            consecutiveThreshold: $this->threshold(),
            channels: $this->csv($this->settings->getString(SettingKey::NotifyChannels)),
            emailRecipients: $this->csv($this->settings->getString(SettingKey::NotifyEmailTo)),
            emailDsn: $this->settings->getString(SettingKey::NotifyEmailDsn),
            chatDsn: $this->settings->getString(SettingKey::NotifyChatDsn),
            webhookUrl: $this->settings->getString(SettingKey::NotifyWebhookUrl),
        );
    }

    private function threshold(): int
    {
        $raw = trim($this->settings->getString(SettingKey::NotifyThreshold));

        if ($raw === '' || !ctype_digit($raw)) {
            return self::DEFAULT_THRESHOLD;
        }

        return max(self::MIN_THRESHOLD, (int) $raw);
    }

    /**
     * @return list<string>
     */
    private function csv(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(string $item): string => trim($item), explode(',', $value)),
            static fn(string $item): bool => $item !== '',
        ));
    }
}
