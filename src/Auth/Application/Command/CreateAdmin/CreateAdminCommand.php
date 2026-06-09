<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\CreateAdmin;

final readonly class CreateAdminCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {}
}
