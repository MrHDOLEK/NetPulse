<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

use DateTimeImmutable;

final readonly class HealthSample
{
    public function __construct(
        public DateTimeImmutable $completedAt,
        public bool $failed,
        public ?bool $healthy,
    ) {}

    public static function completed(DateTimeImmutable $completedAt, ?bool $healthy): self
    {
        return new self($completedAt, false, $healthy);
    }

    public static function failed(DateTimeImmutable $completedAt): self
    {
        return new self($completedAt, true, null);
    }

    public function isHealthy(): bool
    {
        return !$this->failed && $this->healthy === true;
    }

    public function isUnhealthy(): bool
    {
        return $this->failed || $this->healthy === false;
    }
}
