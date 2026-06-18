<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure;

use App\Settings\Application\SettingsException;
use App\Settings\Application\SettingsReader;
use App\Settings\Application\SettingsWriter;
use App\Settings\Domain\AppSetting;
use App\Settings\Domain\AppSettingRepository;
use App\Settings\Domain\SettingKey;
use App\Settings\Infrastructure\Security\SettingsSecretEncryptor;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function in_array;
use function strtolower;
use function trim;

final readonly class AppSettings implements SettingsReader, SettingsWriter
{
    /** @var array<string, string> map of SettingKey value -> its ENV fallback string */
    private array $envFallbacks;

    public function __construct(
        private AppSettingRepository $repository,
        private SettingsSecretEncryptor $encryptor,
        #[Autowire('%env(string:NETPULSE_SITE_NAME)%')]
        string $siteName,
        #[Autowire('%env(string:NETPULSE_TIMEZONE)%')]
        string $timezone,
        #[Autowire('%env(string:OIDC_NAME)%')]
        string $oidcName,
        #[Autowire('%env(string:OIDC_CLIENT_ID)%')]
        string $oidcClientId,
        #[Autowire('%env(string:OIDC_CLIENT_SECRET)%')]
        string $oidcClientSecret,
        #[Autowire('%env(string:OIDC_AUTHORIZATION_URL)%')]
        string $oidcAuthorizationUrl,
        #[Autowire('%env(string:OIDC_TOKEN_URL)%')]
        string $oidcTokenUrl,
        #[Autowire('%env(string:OIDC_USERINFO_URL)%')]
        string $oidcUserInfoUrl,
        #[Autowire('%env(string:OIDC_REDIRECT_URL)%')]
        string $oidcRedirectUrl,
        #[Autowire('%env(string:OIDC_SCOPES)%')]
        string $oidcScopes,
        #[Autowire('%env(string:NOTIFY_ENABLED)%')]
        string $notifyEnabled,
        #[Autowire('%env(string:NOTIFY_CONSECUTIVE_THRESHOLD)%')]
        string $notifyThreshold,
        #[Autowire('%env(string:NOTIFY_CHANNELS)%')]
        string $notifyChannels,
        #[Autowire('%env(string:NOTIFY_EMAIL_TO)%')]
        string $notifyEmailTo,
        #[Autowire('%env(string:MAILER_DSN)%')]
        string $notifyEmailDsn,
        #[Autowire('%env(string:NOTIFY_CHAT_DSN)%')]
        string $notifyChatDsn,
        #[Autowire('%env(string:NOTIFY_WEBHOOK_URL)%')]
        string $notifyWebhookUrl,
    ) {
        $this->envFallbacks = [
            SettingKey::SiteName->value => $siteName,
            SettingKey::Timezone->value => $timezone,
            SettingKey::OidcName->value => $oidcName,
            SettingKey::OidcClientId->value => $oidcClientId,
            SettingKey::OidcClientSecret->value => $oidcClientSecret,
            SettingKey::OidcAuthorizationUrl->value => $oidcAuthorizationUrl,
            SettingKey::OidcTokenUrl->value => $oidcTokenUrl,
            SettingKey::OidcUserInfoUrl->value => $oidcUserInfoUrl,
            SettingKey::OidcRedirectUrl->value => $oidcRedirectUrl,
            SettingKey::OidcScopes->value => $oidcScopes,
            SettingKey::NotifyEnabled->value => $notifyEnabled,
            SettingKey::NotifyThreshold->value => $notifyThreshold,
            SettingKey::NotifyChannels->value => $notifyChannels,
            SettingKey::NotifyEmailTo->value => $notifyEmailTo,
            SettingKey::NotifyEmailDsn->value => $notifyEmailDsn,
            SettingKey::NotifyChatDsn->value => $notifyChatDsn,
            SettingKey::NotifyWebhookUrl->value => $notifyWebhookUrl,
        ];
    }

    public function get(SettingKey $key): ?string
    {
        $stored = $this->repository->find($key);

        if ($stored !== null) {
            if ($stored->isEncrypted()) {
                return $this->encryptor->decrypt($stored->value());
            }

            return $stored->value();
        }

        return $this->envFallbacks[$key->value] ?? null;
    }

    public function getString(SettingKey $key): string
    {
        return $this->get($key) ?? '';
    }

    public function getBool(SettingKey $key): bool
    {
        $value = $this->rawBool($key);

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(SettingKey $key, ?string $value): void
    {
        if ($key->isSecret()) {
            if ($value === null) {
                return;
            }

            if ($value === '') {
                $this->store($key, '', false);

                return;
            }

            if (!$this->encryptor->canEncrypt()) {
                throw SettingsException::encryptionUnavailable();
            }

            $this->store($key, $this->encryptor->encrypt($value), true);

            return;
        }

        $this->store($key, $value === null ? '' : trim($value), false);
    }

    private function store(SettingKey $key, string $value, bool $isEncrypted): void
    {
        $now = new DateTimeImmutable();
        $existing = $this->repository->find($key);

        if ($existing === null) {
            $this->repository->save(new AppSetting($key, $value, $isEncrypted, $now));

            return;
        }

        $existing->changeValue($value, $isEncrypted, $now);
        $this->repository->save($existing);
    }

    private function rawBool(SettingKey $key): string
    {
        $stored = $this->repository->find($key);

        if ($stored !== null) {
            return $stored->value();
        }

        return $this->envFallbacks[$key->value] ?? '';
    }
}
