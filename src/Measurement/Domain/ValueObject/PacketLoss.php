<?php

declare(strict_types=1);

namespace App\Measurement\Domain\ValueObject;

use App\Measurement\Domain\Exception\InvalidPacketLoss;

final readonly class PacketLoss
{
    public function __construct(
        public float $ratio,
    ) {
        if ($this->ratio < 0.0 || $this->ratio > 1.0) {
            throw new InvalidPacketLoss();
        }
    }

    public static function fromOoklaPercent(float $percent): self
    {
        return new self($percent / 100.0);
    }
}
