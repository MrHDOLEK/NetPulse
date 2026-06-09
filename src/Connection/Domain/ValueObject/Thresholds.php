<?php

declare(strict_types=1);

namespace App\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidThresholds;

final readonly class Thresholds
{
    private function __construct(
        private float $minDownloadRatio,
        private float $minUploadRatio,
        private ?float $maxPingMs,
        private ?float $maxJitterMs,
        private ?float $maxPacketLossRatio,
    ) {
        $this->guardRatio("minDownloadRatio", $minDownloadRatio);
        $this->guardRatio("minUploadRatio", $minUploadRatio);
        $this->guardCap("maxPingMs", $maxPingMs);
        $this->guardCap("maxJitterMs", $maxJitterMs);
        $this->guardCap("maxPacketLossRatio", $maxPacketLossRatio);
    }

    public static function default(): self
    {
        return new self(0.7, 0.7, 100.0, 50.0, 0.05);
    }

    public static function of(
        float $minDownloadRatio,
        float $minUploadRatio,
        ?float $maxPingMs,
        ?float $maxJitterMs,
        ?float $maxPacketLossRatio,
    ): self {
        return new self($minDownloadRatio, $minUploadRatio, $maxPingMs, $maxJitterMs, $maxPacketLossRatio);
    }

    public function minDownloadRatio(): float
    {
        return $this->minDownloadRatio;
    }

    public function minUploadRatio(): float
    {
        return $this->minUploadRatio;
    }

    public function maxPingMs(): ?float
    {
        return $this->maxPingMs;
    }

    public function maxJitterMs(): ?float
    {
        return $this->maxJitterMs;
    }

    public function maxPacketLossRatio(): ?float
    {
        return $this->maxPacketLossRatio;
    }

    private function guardRatio(string $field, float $ratio): void
    {
        if ($ratio <= 0.0 || $ratio > 1.0) {
            throw InvalidThresholds::ratioOutOfRange($field, $ratio);
        }
    }

    private function guardCap(string $field, ?float $value): void
    {
        if ($value !== null && $value < 0.0) {
            throw InvalidThresholds::negativeCap($field, $value);
        }
    }
}
