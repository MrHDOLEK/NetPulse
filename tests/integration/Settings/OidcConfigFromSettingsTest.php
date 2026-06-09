<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Settings\Application\SettingsWriter;
use App\Settings\Domain\SettingKey;
use App\Settings\Infrastructure\Oidc\OidcConfigFactory;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OidcConfigFromSettingsTest extends KernelTestCase
{
    private SettingsWriter $writer;
    private OidcConfigFactory $factory;
    private DbalConnection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $writer = $container->get("test." . SettingsWriter::class);
        self::assertInstanceOf(SettingsWriter::class, $writer);
        $this->writer = $writer;

        $factory = $container->get(OidcConfigFactory::class);
        self::assertInstanceOf(OidcConfigFactory::class, $factory);
        $this->factory = $factory;

        $this->db = $container->get("doctrine.dbal.default_connection");
    }

    protected function tearDown(): void
    {
        $this->db->executeStatement("DELETE FROM app_settings");

        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->clear();

        parent::tearDown();
    }

    public function testDisabledWhenNothingSavedAndEnvEmpty(): void
    {
        self::assertFalse($this->factory->create()->isEnabled());
    }

    public function testSavedSettingsEnableSsoWithoutRestart(): void
    {
        $this->saveFullConfig();

        $config = $this->factory->create();
        self::assertTrue($config->isEnabled());
        self::assertSame("client-from-db", $config->clientId);
        self::assertSame("secret-from-db", $config->clientSecret);
        self::assertSame("https://idp.db/authorize", $config->authorizationUrl);
        self::assertSame("DB SSO", $config->displayName());
    }

    public function testMissingAnyRequiredFieldKeepsSsoDisabled(): void
    {
        $this->saveFullConfig();

        $this->writer->set(SettingKey::OidcTokenUrl, "");

        self::assertFalse($this->factory->create()->isEnabled());
    }

    public function testEnableTogglePersistedFalseForcesSsoOff(): void
    {
        $this->saveFullConfig();

        $this->writer->set(SettingKey::OidcEnabled, "0");

        self::assertFalse($this->factory->create()->isEnabled());

        $this->writer->set(SettingKey::OidcEnabled, "1");
        self::assertTrue($this->factory->create()->isEnabled());
    }

    private function saveFullConfig(): void
    {
        $this->writer->set(SettingKey::OidcEnabled, "1");
        $this->writer->set(SettingKey::OidcName, "DB SSO");
        $this->writer->set(SettingKey::OidcClientId, "client-from-db");
        $this->writer->set(SettingKey::OidcClientSecret, "secret-from-db");
        $this->writer->set(SettingKey::OidcAuthorizationUrl, "https://idp.db/authorize");
        $this->writer->set(SettingKey::OidcTokenUrl, "https://idp.db/token");
        $this->writer->set(SettingKey::OidcUserInfoUrl, "https://idp.db/userinfo");
        $this->writer->set(SettingKey::OidcRedirectUrl, "https://app.db/login/oidc/callback");
        $this->writer->set(SettingKey::OidcScopes, "openid email profile");
    }
}
