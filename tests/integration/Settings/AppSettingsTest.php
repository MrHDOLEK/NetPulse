<?php

declare(strict_types=1);

namespace App\Tests\Integration\Settings;

use App\Settings\Application\SettingsReader;
use App\Settings\Application\SettingsWriter;
use App\Settings\Domain\SettingKey;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AppSettingsTest extends KernelTestCase
{
    private const string SECRET = 'super-secret-oidc-value-123';

    private SettingsReader $reader;
    private SettingsWriter $writer;
    private DbalConnection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $reader = $container->get('test.' . SettingsReader::class);
        self::assertInstanceOf(SettingsReader::class, $reader);
        $writer = $container->get('test.' . SettingsWriter::class);
        self::assertInstanceOf(SettingsWriter::class, $writer);

        $this->reader = $reader;
        $this->writer = $writer;
        $this->db = $container->get('doctrine.dbal.default_connection');
    }

    protected function tearDown(): void
    {
        $this->db->executeStatement('DELETE FROM app_settings');

        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->clear();

        parent::tearDown();
    }

    public function testSecretSettingIsStoredEncryptedAndDecryptsBack(): void
    {
        $this->writer->set(SettingKey::OidcClientSecret, self::SECRET);

        self::assertSame(self::SECRET, $this->reader->get(SettingKey::OidcClientSecret));

        $row = $this->db->fetchAssociative('SELECT value, is_encrypted FROM app_settings WHERE setting_key = ?', [
            SettingKey::OidcClientSecret->value,
        ]);
        self::assertIsArray($row);
        self::assertIsString($row['value']);
        self::assertNotSame(self::SECRET, $row['value']);
        self::assertStringNotContainsString(self::SECRET, $row['value']);
        self::assertSame(1, (int) $row['is_encrypted']);
    }

    public function testPlainSettingIsStoredInClearAndWins(): void
    {
        $this->writer->set(SettingKey::SiteName, 'Acme Pulse');

        self::assertSame('Acme Pulse', $this->reader->getString(SettingKey::SiteName));

        $row = $this->db->fetchAssociative('SELECT value, is_encrypted FROM app_settings WHERE setting_key = ?', [
            SettingKey::SiteName->value,
        ]);
        self::assertIsArray($row);
        self::assertSame('Acme Pulse', $row['value']);
        self::assertSame(0, (int) $row['is_encrypted']);
    }

    public function testDbOverEnvFallback(): void
    {
        self::assertSame('', $this->reader->getString(SettingKey::SiteName));

        $this->writer->set(SettingKey::SiteName, 'From DB');
        self::assertSame('From DB', $this->reader->getString(SettingKey::SiteName));
    }

    public function testWriteOnlySecretNullKeepsExistingValue(): void
    {
        $this->writer->set(SettingKey::OidcClientSecret, self::SECRET);

        $this->writer->set(SettingKey::OidcClientSecret, null);

        self::assertSame(self::SECRET, $this->reader->get(SettingKey::OidcClientSecret));
    }

    public function testExplicitEmptyStringClearsSecret(): void
    {
        $this->writer->set(SettingKey::OidcClientSecret, self::SECRET);
        $this->writer->set(SettingKey::OidcClientSecret, '');

        self::assertSame('', $this->reader->getString(SettingKey::OidcClientSecret));

        $row = $this->db->fetchAssociative('SELECT value, is_encrypted FROM app_settings WHERE setting_key = ?', [
            SettingKey::OidcClientSecret->value,
        ]);
        self::assertIsArray($row);
        self::assertSame('', $row['value']);
        self::assertSame(0, (int) $row['is_encrypted']);
    }

    public function testGetBoolReadsTheStoredFlag(): void
    {
        self::assertFalse($this->reader->getBool(SettingKey::OidcEnabled));

        $this->writer->set(SettingKey::OidcEnabled, '1');
        self::assertTrue($this->reader->getBool(SettingKey::OidcEnabled));

        $this->writer->set(SettingKey::OidcEnabled, '0');
        self::assertFalse($this->reader->getBool(SettingKey::OidcEnabled));
    }
}
