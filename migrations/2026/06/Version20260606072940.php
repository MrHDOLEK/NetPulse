<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606072940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core entity tables: probes, connections, measurements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE probes (name VARCHAR(255) NOT NULL, labels CLOB NOT NULL, token_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL, id VARCHAR(36) NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql(
            'CREATE TABLE connections (probe_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, isp VARCHAR(255) NOT NULL, color VARCHAR(32) NOT NULL, labels CLOB NOT NULL, server_pool CLOB NOT NULL, enabled BOOLEAN NOT NULL, id CHAR(36) NOT NULL, expected_download_bits INTEGER NOT NULL, expected_upload_bits INTEGER NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql('CREATE INDEX idx_connections_probe_id ON connections (probe_id)');
        $this->addSql(
            'CREATE TABLE measurements (probe_id VARCHAR(36) NOT NULL, connection_id CHAR(36) NOT NULL, status VARCHAR(16) NOT NULL, scheduled BOOLEAN NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME NOT NULL, server_id VARCHAR(64) NOT NULL, server_name VARCHAR(255) NOT NULL, server_location VARCHAR(255) NOT NULL, server_host VARCHAR(255) NOT NULL, isp VARCHAR(255) NOT NULL, download_bits BIGINT DEFAULT NULL, upload_bits BIGINT DEFAULT NULL, download_bytes BIGINT DEFAULT NULL, upload_bytes BIGINT DEFAULT NULL, ping DOUBLE PRECISION DEFAULT NULL, ping_low DOUBLE PRECISION DEFAULT NULL, ping_high DOUBLE PRECISION DEFAULT NULL, jitter DOUBLE PRECISION DEFAULT NULL, download_jitter DOUBLE PRECISION DEFAULT NULL, upload_jitter DOUBLE PRECISION DEFAULT NULL, download_latency_iqm DOUBLE PRECISION DEFAULT NULL, download_latency_low DOUBLE PRECISION DEFAULT NULL, download_latency_high DOUBLE PRECISION DEFAULT NULL, upload_latency_iqm DOUBLE PRECISION DEFAULT NULL, upload_latency_low DOUBLE PRECISION DEFAULT NULL, upload_latency_high DOUBLE PRECISION DEFAULT NULL, packet_loss_ratio DOUBLE PRECISION DEFAULT NULL, data_used_download BIGINT NOT NULL, data_used_upload BIGINT NOT NULL, download_elapsed INTEGER NOT NULL, upload_elapsed INTEGER NOT NULL, result_url VARCHAR(512) DEFAULT NULL, raw_payload CLOB NOT NULL, healthy BOOLEAN DEFAULT NULL, id VARCHAR(36) NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql(
            'CREATE INDEX idx_measurement_connection_completed ON measurements (connection_id, completed_at)',
        );
        $this->addSql('CREATE INDEX idx_measurement_probe_status ON measurements (probe_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE measurements');
        $this->addSql('DROP TABLE connections');
        $this->addSql('DROP TABLE probes');
    }
}
