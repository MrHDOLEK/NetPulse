<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Oidc;

use App\Auth\Infrastructure\Oidc\OidcConfig;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;

final readonly class OidcConfigFactory
{
    public function __construct(
        private SettingsReader $settings,
    ) {}

    public function create(): OidcConfig
    {
        $clientId = $this->settings->getString(SettingKey::OidcClientId);
        $clientSecret = $this->settings->getString(SettingKey::OidcClientSecret);
        $authorizationUrl = $this->settings->getString(SettingKey::OidcAuthorizationUrl);
        $tokenUrl = $this->settings->getString(SettingKey::OidcTokenUrl);
        $userInfoUrl = $this->settings->getString(SettingKey::OidcUserInfoUrl);

        if ($this->hasEnableToggle() && !$this->settings->getBool(SettingKey::OidcEnabled)) {
            $clientId = "";
            $clientSecret = "";
        }

        return new OidcConfig(
            $clientId,
            $clientSecret,
            $authorizationUrl,
            $tokenUrl,
            $userInfoUrl,
            $this->settings->getString(SettingKey::OidcRedirectUrl),
            $this->settings->getString(SettingKey::OidcScopes),
            $this->settings->getString(SettingKey::OidcName),
        );
    }

    private function hasEnableToggle(): bool
    {
        return $this->settings->get(SettingKey::OidcEnabled) !== null;
    }
}
