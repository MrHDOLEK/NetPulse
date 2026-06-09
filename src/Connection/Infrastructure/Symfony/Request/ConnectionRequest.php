<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Request;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Shared\Infrastructure\Utils\Request\RequestInterface;

final class ConnectionRequest implements RequestInterface
{
    public string $probeId = "";
    public string $name = "";
    public string $isp = "";
    public string $color = ConnectionColor::Primary->value;
    public int $downloadMbps = 0;
    public int $uploadMbps = 0;
    public string $labels = "";
    public string $serverPool = "";
    public string $scheduleMode = "even";
    public string $cron = "";
    public int $testsPerDay = 24;
    public int $jitter = 120;
    public ?float $minDownloadRatio = null;
    public ?float $minUploadRatio = null;
    public ?float $maxPingMs;
    public ?float $maxJitterMs;
    public ?float $maxPacketLossRatio;
    public ?int $adaptiveIntervalSeconds = null;
    public ?int $recoveryHealthyCount = null;
    public ?int $maxConsecutiveFailures = null;

    public function __construct()
    {
        $defaults = Thresholds::default();
        $this->maxPingMs = $defaults->maxPingMs();
        $this->maxJitterMs = $defaults->maxJitterMs();
        $this->maxPacketLossRatio = $defaults->maxPacketLossRatio();
    }
}
