<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606093123 extends AbstractMigration
{
    private const string DEFAULT_SCHEDULE = '{"mode":"even","cronExpressions":[],"testsPerDay":24,"jitterSeconds":120}';

    public function getDescription(): string
    {
        return "Add scheduling config (schedule JSON column) to connections; backfill existing rows with even/24-per-day.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TEMPORARY TABLE __temp__connections AS SELECT probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits FROM connections");
        $this->addSql("DROP TABLE connections");
        $this->addSql("CREATE TABLE connections (probe_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, isp VARCHAR(255) NOT NULL, color VARCHAR(32) NOT NULL, labels CLOB NOT NULL, server_pool CLOB NOT NULL, enabled BOOLEAN NOT NULL, id CHAR(36) NOT NULL, expected_download_bits INTEGER NOT NULL, expected_upload_bits INTEGER NOT NULL, schedule CLOB NOT NULL, PRIMARY KEY (id))");
        $this->addSql(
            "INSERT INTO connections (probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits, schedule) "
            . "SELECT probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits, :schedule FROM __temp__connections",
            ["schedule" => self::DEFAULT_SCHEDULE],
        );
        $this->addSql("DROP TABLE __temp__connections");
        $this->addSql("CREATE INDEX idx_connections_probe_id ON connections (probe_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("CREATE TEMPORARY TABLE __temp__connections AS SELECT probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits FROM connections");
        $this->addSql("DROP TABLE connections");
        $this->addSql("CREATE TABLE connections (probe_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, isp VARCHAR(255) NOT NULL, color VARCHAR(32) NOT NULL, labels CLOB NOT NULL, server_pool CLOB NOT NULL, enabled BOOLEAN NOT NULL, id CHAR(36) NOT NULL, expected_download_bits INTEGER NOT NULL, expected_upload_bits INTEGER NOT NULL, PRIMARY KEY (id))");
        $this->addSql("INSERT INTO connections (probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits) SELECT probe_id, name, isp, color, labels, server_pool, enabled, id, expected_download_bits, expected_upload_bits FROM __temp__connections");
        $this->addSql("DROP TABLE __temp__connections");
        $this->addSql("CREATE INDEX idx_connections_probe_id ON connections (probe_id)");
    }
}
