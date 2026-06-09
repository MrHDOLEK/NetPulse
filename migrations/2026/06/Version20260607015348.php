<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607015348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create users table (ORM-mapped User entity) with unique index on email.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL, created_at DATETIME NOT NULL, id CHAR(36) NOT NULL, PRIMARY KEY (id))");
        $this->addSql("CREATE UNIQUE INDEX uniq_users_email ON users (email)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE users");
    }
}
