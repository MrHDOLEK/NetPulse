<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606183541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification_send_counts per-(kind, channel, status) counter table (ORM-mapped entity).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE notification_send_counts (id INTEGER NOT NULL, kind VARCHAR(32) NOT NULL, channel VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, total INTEGER NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_notification_send_counts_kind_channel_status ON notification_send_counts (kind, channel, status)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_send_counts');
    }
}
