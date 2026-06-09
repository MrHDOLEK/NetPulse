<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

final readonly class DueDecision
{
    private function __construct(
        public ?string $serverId,
    ) {}

    public static function due(?string $serverId): self
    {
        return new self($serverId);
    }
}
