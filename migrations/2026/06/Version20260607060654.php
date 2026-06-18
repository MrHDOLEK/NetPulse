<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607060654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create due_now_markers table backing on-demand run-test (one-shot due markers).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE due_now_markers (connection_id CHAR(36) NOT NULL, requested_at DATETIME NOT NULL, PRIMARY KEY (connection_id))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE due_now_markers');
    }
}
