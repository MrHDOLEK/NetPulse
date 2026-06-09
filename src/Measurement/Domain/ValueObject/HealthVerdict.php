<?php

declare(strict_types=1);

namespace App\Measurement\Domain\ValueObject;

use App\Measurement\Domain\Collection\ThresholdBreachCollection;
use App\Measurement\Domain\Enum\ThresholdBreach;

final readonly class HealthVerdict
{
    private function __construct(
        private bool $healthy,
        private ThresholdBreachCollection $breaches,
    ) {}

    public static function healthy(): self
    {
        return new self(true, ThresholdBreachCollection::of());
    }

    public static function unhealthy(ThresholdBreach ...$breaches): self
    {
        return new self(false, ThresholdBreachCollection::of(...$breaches));
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function breaches(): ThresholdBreachCollection
    {
        return $this->breaches;
    }
}
