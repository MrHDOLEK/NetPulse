<?php

declare(strict_types=1);

namespace App\Auth\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class HashedPassword implements Stringable
{
    private function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromHash(string $hash): self
    {
        if ($hash === "") {
            throw new InvalidArgumentException("Hashed password must not be empty.");
        }

        return new self($hash);
    }

    public function value(): string
    {
        return $this->value;
    }
}
