<?php

declare(strict_types=1);

namespace App\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidAdaptivePolicy;

final readonly class AdaptivePolicy
{
    private function __construct(
        private int $adaptiveIntervalSeconds,
        private int $recoveryHealthyCount,
        private int $maxConsecutiveFailures,
    ) {
        $this->guardPositive('adaptiveIntervalSeconds', $adaptiveIntervalSeconds);
        $this->guardPositive('recoveryHealthyCount', $recoveryHealthyCount);
        $this->guardPositive('maxConsecutiveFailures', $maxConsecutiveFailures);
    }

    public static function default(): self
    {
        return new self(300, 3, 5);
    }

    public static function of(
        int $adaptiveIntervalSeconds,
        int $recoveryHealthyCount,
        int $maxConsecutiveFailures,
    ): self {
        return new self($adaptiveIntervalSeconds, $recoveryHealthyCount, $maxConsecutiveFailures);
    }

    public function adaptiveIntervalSeconds(): int
    {
        return $this->adaptiveIntervalSeconds;
    }

    public function recoveryHealthyCount(): int
    {
        return $this->recoveryHealthyCount;
    }

    public function maxConsecutiveFailures(): int
    {
        return $this->maxConsecutiveFailures;
    }

    private function guardPositive(string $field, int $value): void
    {
        if ($value < 1) {
            throw InvalidAdaptivePolicy::tooLow($field, $value);
        }
    }
}
