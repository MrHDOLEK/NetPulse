<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RegenerateRecoveryCodes;

final readonly class RegenerateRecoveryCodesCommand
{
    /**
     * @param list<string> $hashedRecoveryCodes
     */
    public function __construct(
        public string $userId,
        public array $hashedRecoveryCodes,
    ) {}
}
