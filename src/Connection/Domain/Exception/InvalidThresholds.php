<?php

declare(strict_types=1);

namespace App\Connection\Domain\Exception;

use App\Shared\Domain\DomainException;

final class InvalidThresholds extends DomainException
{
    public static function ratioOutOfRange(string $field, float $ratio): self
    {
        return new self($field . ' must be in the (0, 1] range, got ' . $ratio . '.');
    }

    public static function negativeCap(string $field, float $value): self
    {
        return new self($field . ' must be >= 0 when set, got ' . $value . '.');
    }
}
