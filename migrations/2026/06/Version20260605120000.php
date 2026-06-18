<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create remote_write_failures single-row counter table (ORM-mapped entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE remote_write_failures (id INTEGER NOT NULL, total INTEGER NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql('INSERT INTO remote_write_failures (id, total) VALUES (1, 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE remote_write_failures');
    }
}
