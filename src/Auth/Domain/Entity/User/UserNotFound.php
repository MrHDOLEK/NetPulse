<?php

declare(strict_types=1);

namespace App\Auth\Domain\Entity\User;

use App\Shared\Domain\NotFoundException;

use function sprintf;

final class UserNotFound extends NotFoundException
{
    public static function withId(UserId $id): self
    {
        return new self(sprintf("User %s not found.", $id->toString()));
    }
}
