<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create run_states per-connection run-test progress table (ORM-mapped entity).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE run_states (connection_id CHAR(36) NOT NULL, phase VARCHAR(16) NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (connection_id))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE run_states');
    }
}
