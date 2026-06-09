<?php

declare(strict_types=1);

namespace App\Connection\Domain\Exception;

use App\Shared\Domain\DomainException;

final class InvalidAdaptivePolicy extends DomainException
{
    public static function tooLow(string $field, int $value): self
    {
        return new self($field . " must be >= 1, got " . $value . ".");
    }
}
