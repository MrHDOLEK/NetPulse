<?php

declare(strict_types=1);

namespace App\Auth\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

use function filter_var;
use function sprintf;
use function strtolower;

use const FILTER_VALIDATE_EMAIL;

final readonly class Email implements Stringable
{
    /** @var non-empty-string */
    private string $value;

    public function __construct(string $value)
    {
        $normalized = filter_var(strtolower($value), FILTER_VALIDATE_EMAIL);

        if ($normalized === false || $normalized === '') {
            throw new InvalidArgumentException(sprintf('Invalid email address: "%s".', $value));
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return non-empty-string
     */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
