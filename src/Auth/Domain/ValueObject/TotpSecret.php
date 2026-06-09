<?php

declare(strict_types=1);

namespace App\Auth\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class TotpSecret implements Stringable
{
    public function __construct(
        private string $value,
    ) {
        if ($value === "") {
            throw new InvalidArgumentException("TOTP secret must not be empty.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
