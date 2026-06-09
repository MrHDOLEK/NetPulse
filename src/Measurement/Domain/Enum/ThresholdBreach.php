<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Enum;

enum ThresholdBreach: string
{
    case TestFailed = "test_failed";
    case DownloadBelow = "download_below";
    case UploadBelow = "upload_below";
    case PingHigh = "ping_high";
    case JitterHigh = "jitter_high";
    case PacketLossHigh = "packet_loss_high";
}
