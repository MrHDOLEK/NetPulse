<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class ProbeLiveness
{
    public function __construct(
        public string $probeId,
        public string $name,
        public bool $isOnline,
        public ?int $lastPollAtUnix,
        public ?int $minutesSincePoll,
    ) {}
}
