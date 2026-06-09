<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add nullable last_poll_at to probes (agent /due liveness) and backfill from created_at.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE probes ADD COLUMN last_poll_at DATETIME DEFAULT NULL");
        $this->addSql("UPDATE probes SET last_poll_at = created_at");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE probes DROP COLUMN last_poll_at");
    }
}
