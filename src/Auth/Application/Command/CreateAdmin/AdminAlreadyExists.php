<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\CreateAdmin;

use RuntimeException;

use function sprintf;

final class AdminAlreadyExists extends RuntimeException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('An admin account already exists for "%s".', $email));
    }
}
