<?php

declare(strict_types=1);

namespace App\Probe\Domain\ValueObject;

use function base64_encode;
use function random_bytes;
use function rtrim;
use function strtr;

final readonly class ProbeToken
{
    public function __construct(
        private string $plaintext,
    ) {}

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "="));
    }

    public function toString(): string
    {
        return $this->plaintext;
    }
}
