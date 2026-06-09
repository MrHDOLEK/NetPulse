<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607200027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add opt-in TOTP 2FA columns to users (encrypted secret + hashed recovery codes), both nullable.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD COLUMN recovery_codes CLOB DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN recovery_codes");
        $this->addSql("ALTER TABLE users DROP COLUMN totp_secret");
    }
}
