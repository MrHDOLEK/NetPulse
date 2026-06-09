<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\EnableTotp;

final readonly class EnableTotpCommand
{
    /**
     * @param list<string> $hashedRecoveryCodes
     */
    public function __construct(
        public string $userId,
        public string $secret,
        public array $hashedRecoveryCodes,
    ) {}
}
