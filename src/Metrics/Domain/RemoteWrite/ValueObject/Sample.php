<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\ValueObject;

final readonly class Sample
{
    public function __construct(
        public float $value,
        public int $timestampMs,
    ) {}
}
