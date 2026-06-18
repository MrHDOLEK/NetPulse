<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use function base64_encode;
use function random_bytes;
use function rtrim;
use function strtr;

final readonly class ShareToken
{
    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
