<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

enum MeasurementSort: string
{
    case CompletedAtDesc = "completed_at_desc";
    case CompletedAtAsc = "completed_at_asc";
    case DownloadDesc = "download_desc";
    case DownloadAsc = "download_asc";
    case UploadDesc = "upload_desc";
    case UploadAsc = "upload_asc";
    case PingDesc = "ping_desc";
    case PingAsc = "ping_asc";
    case JitterDesc = "jitter_desc";
    case JitterAsc = "jitter_asc";
    case LossDesc = "loss_desc";
    case LossAsc = "loss_asc";

    public static function default(): self
    {
        return self::CompletedAtDesc;
    }

    /**
     * @return array{0: string, 1: 'ASC'|'DESC'}
     */
    public function orderBy(): array
    {
        return match ($this) {
            self::CompletedAtDesc => ["measurement.completedAt", "DESC"],
            self::CompletedAtAsc => ["measurement.completedAt", "ASC"],
            self::DownloadDesc => ["measurement.downloadBits", "DESC"],
            self::DownloadAsc => ["measurement.downloadBits", "ASC"],
            self::UploadDesc => ["measurement.uploadBits", "DESC"],
            self::UploadAsc => ["measurement.uploadBits", "ASC"],
            self::PingDesc => ["measurement.ping", "DESC"],
            self::PingAsc => ["measurement.ping", "ASC"],
            self::JitterDesc => ["measurement.jitter", "DESC"],
            self::JitterAsc => ["measurement.jitter", "ASC"],
            self::LossDesc => ["measurement.packetLossRatio", "DESC"],
            self::LossAsc => ["measurement.packetLossRatio", "ASC"],
        };
    }
}
