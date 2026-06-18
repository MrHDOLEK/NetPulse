<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607171133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable forced_server_id to due_now_markers so a run-test request can pin a specific Ookla server.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE due_now_markers ADD COLUMN forced_server_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__due_now_markers AS SELECT requested_at, connection_id FROM due_now_markers',
        );
        $this->addSql('DROP TABLE due_now_markers');
        $this->addSql(
            'CREATE TABLE due_now_markers (requested_at DATETIME NOT NULL, connection_id CHAR(36) NOT NULL, PRIMARY KEY (connection_id))',
        );
        $this->addSql(
            'INSERT INTO due_now_markers (requested_at, connection_id) SELECT requested_at, connection_id FROM __temp__due_now_markers',
        );
        $this->addSql('DROP TABLE __temp__due_now_markers');
    }
}
