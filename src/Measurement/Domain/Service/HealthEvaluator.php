<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Service;

use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\Enum\ThresholdBreach;
use App\Measurement\Domain\ValueObject\HealthVerdict;

final class HealthEvaluator
{
    public function evaluate(Measurement $measurement, Thresholds $thresholds, ExpectedSpeed $expected): HealthVerdict
    {
        if ($measurement->status() === MeasurementStatus::Failed) {
            return HealthVerdict::unhealthy(ThresholdBreach::TestFailed);
        }

        $breaches = [];

        $bandwidth = $measurement->bandwidth();

        if (
            $expected->expectedDownloadBits > 0
            && (
                $bandwidth === null
                || $bandwidth->downloadBits < ($thresholds->minDownloadRatio() * $expected->expectedDownloadBits)
            )
        ) {
            $breaches[] = ThresholdBreach::DownloadBelow;
        }

        if (
            $expected->expectedUploadBits > 0
            && (
                $bandwidth === null
                || $bandwidth->uploadBits < ($thresholds->minUploadRatio() * $expected->expectedUploadBits)
            )
        ) {
            $breaches[] = ThresholdBreach::UploadBelow;
        }

        $latency = $measurement->latency();

        $maxPingMs = $thresholds->maxPingMs();

        if ($maxPingMs !== null && ($latency === null || $latency->ping > $maxPingMs)) {
            $breaches[] = ThresholdBreach::PingHigh;
        }

        $maxJitterMs = $thresholds->maxJitterMs();

        if ($maxJitterMs !== null && ($latency === null || $latency->jitter > $maxJitterMs)) {
            $breaches[] = ThresholdBreach::JitterHigh;
        }

        $maxPacketLossRatio = $thresholds->maxPacketLossRatio();
        $packetLoss = $measurement->packetLoss();

        if ($maxPacketLossRatio !== null && ($packetLoss === null || $packetLoss->ratio > $maxPacketLossRatio)) {
            $breaches[] = ThresholdBreach::PacketLossHigh;
        }

        if ($breaches === []) {
            return HealthVerdict::healthy();
        }

        return HealthVerdict::unhealthy(...$breaches);
    }
}
