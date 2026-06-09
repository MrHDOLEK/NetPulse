<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create app_settings: the persisted, DB-over-ENV settings store (General + SSO), with secret values encrypted at rest.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE app_settings (setting_key VARCHAR(100) NOT NULL, value CLOB NOT NULL, "
            . "is_encrypted BOOLEAN NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (setting_key))",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE app_settings");
    }
}
