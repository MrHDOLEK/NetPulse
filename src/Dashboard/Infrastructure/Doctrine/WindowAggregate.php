<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

final readonly class WindowAggregate
{
    public function __construct(
        public int $testsRun,
        public int $incidents,
        public int $healthyCount,
        public bool $latestFailed,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, false);
    }

    public static function first(bool $latestFailed): self
    {
        return new self(0, 0, 0, $latestFailed);
    }

    public function add(bool $isFailed, bool $isIncident, bool $isHealthy): self
    {
        return new self(
            testsRun: $this->testsRun + 1,
            incidents: $this->incidents + ($isIncident ? 1 : 0),
            healthyCount: $this->healthyCount + ($isHealthy ? 1 : 0),
            latestFailed: $this->latestFailed,
        );
    }

    public function uptimePct(): float
    {
        if ($this->testsRun === 0) {
            return 0.0;
        }

        return ($this->healthyCount / $this->testsRun) * 100.0;
    }
}
