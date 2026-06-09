<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ResetPassword;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {}
}
