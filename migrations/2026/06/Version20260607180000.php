<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable, unique share_token column to measurements backing the public /r/{token} page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE measurements ADD COLUMN share_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_measurement_share_token ON measurements (share_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_measurement_share_token');
        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__measurements AS SELECT probe_id, connection_id, status, scheduled, started_at, completed_at, server_id, server_name, server_location, server_host, isp, download_bits, upload_bits, download_bytes, upload_bytes, ping, ping_low, ping_high, jitter, download_jitter, upload_jitter, download_latency_iqm, download_latency_low, download_latency_high, upload_latency_iqm, upload_latency_low, upload_latency_high, packet_loss_ratio, data_used_download, data_used_upload, download_elapsed, upload_elapsed, result_url, raw_payload, healthy, id FROM measurements',
        );
        $this->addSql('DROP TABLE measurements');
        $this->addSql(
            'CREATE TABLE measurements (probe_id VARCHAR(36) NOT NULL, connection_id CHAR(36) NOT NULL, status VARCHAR(16) NOT NULL, scheduled BOOLEAN NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME NOT NULL, server_id VARCHAR(64) NOT NULL, server_name VARCHAR(255) NOT NULL, server_location VARCHAR(255) NOT NULL, server_host VARCHAR(255) NOT NULL, isp VARCHAR(255) NOT NULL, download_bits BIGINT DEFAULT NULL, upload_bits BIGINT DEFAULT NULL, download_bytes BIGINT DEFAULT NULL, upload_bytes BIGINT DEFAULT NULL, ping DOUBLE PRECISION DEFAULT NULL, ping_low DOUBLE PRECISION DEFAULT NULL, ping_high DOUBLE PRECISION DEFAULT NULL, jitter DOUBLE PRECISION DEFAULT NULL, download_jitter DOUBLE PRECISION DEFAULT NULL, upload_jitter DOUBLE PRECISION DEFAULT NULL, download_latency_iqm DOUBLE PRECISION DEFAULT NULL, download_latency_low DOUBLE PRECISION DEFAULT NULL, download_latency_high DOUBLE PRECISION DEFAULT NULL, upload_latency_iqm DOUBLE PRECISION DEFAULT NULL, upload_latency_low DOUBLE PRECISION DEFAULT NULL, upload_latency_high DOUBLE PRECISION DEFAULT NULL, packet_loss_ratio DOUBLE PRECISION DEFAULT NULL, data_used_download BIGINT NOT NULL, data_used_upload BIGINT NOT NULL, download_elapsed INTEGER NOT NULL, upload_elapsed INTEGER NOT NULL, result_url VARCHAR(512) DEFAULT NULL, raw_payload CLOB NOT NULL, healthy BOOLEAN DEFAULT NULL, id VARCHAR(36) NOT NULL, PRIMARY KEY (id))',
        );
        $this->addSql(
            'INSERT INTO measurements (probe_id, connection_id, status, scheduled, started_at, completed_at, server_id, server_name, server_location, server_host, isp, download_bits, upload_bits, download_bytes, upload_bytes, ping, ping_low, ping_high, jitter, download_jitter, upload_jitter, download_latency_iqm, download_latency_low, download_latency_high, upload_latency_iqm, upload_latency_low, upload_latency_high, packet_loss_ratio, data_used_download, data_used_upload, download_elapsed, upload_elapsed, result_url, raw_payload, healthy, id) SELECT probe_id, connection_id, status, scheduled, started_at, completed_at, server_id, server_name, server_location, server_host, isp, download_bits, upload_bits, download_bytes, upload_bytes, ping, ping_low, ping_high, jitter, download_jitter, upload_jitter, download_latency_iqm, download_latency_low, download_latency_high, upload_latency_iqm, upload_latency_low, upload_latency_high, packet_loss_ratio, data_used_download, data_used_upload, download_elapsed, upload_elapsed, result_url, raw_payload, healthy, id FROM __temp__measurements',
        );
        $this->addSql('DROP TABLE __temp__measurements');
        $this->addSql(
            'CREATE INDEX idx_measurement_connection_completed ON measurements (connection_id, completed_at)',
        );
        $this->addSql('CREATE INDEX idx_measurement_probe_status ON measurements (probe_id, status)');
    }
}
