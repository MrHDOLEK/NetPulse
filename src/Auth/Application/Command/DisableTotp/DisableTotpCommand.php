<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\DisableTotp;

final readonly class DisableTotpCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
