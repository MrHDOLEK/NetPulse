<?php

declare(strict_types=1);

namespace App\Auth\Application\RecoveryCode;

final readonly class GeneratedRecoveryCodes
{
    /**
     * @param list<string> $plain
     * @param list<string> $hashed
     */
    public function __construct(
        public array $plain,
        public array $hashed,
    ) {}
}
