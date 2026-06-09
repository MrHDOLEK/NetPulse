<?php

declare(strict_types=1);

namespace App\Settings\Domain;

enum SettingKey: string
{
    case SiteName = "general.site_name";
    case Timezone = "general.timezone";    
    case OidcEnabled = "oidc.enabled";
    case OidcName = "oidc.name";
    case OidcClientId = "oidc.client_id";
    case OidcClientSecret = "oidc.client_secret";
    case OidcAuthorizationUrl = "oidc.authorization_url";
    case OidcTokenUrl = "oidc.token_url";
    case OidcUserInfoUrl = "oidc.userinfo_url";
    case OidcRedirectUrl = "oidc.redirect_url";
    case OidcScopes = "oidc.scopes";    
    case NotifyEnabled = "notify.enabled";
    case NotifyThreshold = "notify.threshold";
    case NotifyChannels = "notify.channels";
    case NotifyEmailTo = "notify.email.to";
    case NotifyEmailDsn = "notify.email.dsn";
    case NotifyChatDsn = "notify.chat.dsn";
    case NotifyWebhookUrl = "notify.webhook.url";

    public function isSecret(): bool
    {
        return match ($this) {
            self::OidcClientSecret,
            self::NotifyEmailDsn,
            self::NotifyChatDsn,
            self::NotifyWebhookUrl => true,
            default => false,
        };
    }

    public function envFallback(): ?string
    {
        return match ($this) {
            self::SiteName => "NETPULSE_SITE_NAME",
            self::Timezone => "NETPULSE_TIMEZONE",
            self::OidcName => "OIDC_NAME",
            self::OidcClientId => "OIDC_CLIENT_ID",
            self::OidcClientSecret => "OIDC_CLIENT_SECRET",
            self::OidcAuthorizationUrl => "OIDC_AUTHORIZATION_URL",
            self::OidcTokenUrl => "OIDC_TOKEN_URL",
            self::OidcUserInfoUrl => "OIDC_USERINFO_URL",
            self::OidcRedirectUrl => "OIDC_REDIRECT_URL",
            self::OidcScopes => "OIDC_SCOPES",
            self::OidcEnabled => null,
            self::NotifyEnabled => "NOTIFY_ENABLED",
            self::NotifyThreshold => "NOTIFY_CONSECUTIVE_THRESHOLD",
            self::NotifyChannels => "NOTIFY_CHANNELS",
            self::NotifyEmailTo => "NOTIFY_EMAIL_TO",
            self::NotifyEmailDsn => "MAILER_DSN",
            self::NotifyChatDsn => "NOTIFY_CHAT_DSN",
            self::NotifyWebhookUrl => "NOTIFY_WEBHOOK_URL",
        };
    }
}
