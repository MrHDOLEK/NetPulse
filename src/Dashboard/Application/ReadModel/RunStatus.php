<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class RunStatus
{
    public const string IDLE = 'idle';

    public function __construct(
        public string $state,
        public ?int $startedAtUnix,
    ) {}

    public static function idle(): self
    {
        return new self(self::IDLE, null);
    }
}
