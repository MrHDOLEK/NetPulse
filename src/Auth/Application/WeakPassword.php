<?php

declare(strict_types=1);

namespace App\Auth\Application;

use InvalidArgumentException;

use function sprintf;

final class WeakPassword extends InvalidArgumentException
{
    public const int MIN_LENGTH = 12;

    public static function tooShort(): self
    {
        return new self(sprintf("Password must be at least %d characters long.", self::MIN_LENGTH));
    }
}
