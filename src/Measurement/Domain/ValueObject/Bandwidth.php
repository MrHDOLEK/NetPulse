<?php

declare(strict_types=1);

namespace App\Measurement\Domain\ValueObject;

final readonly class Bandwidth
{
    public function __construct(
        public int $downloadBits,
        public int $uploadBits,
        public int $downloadBytes,
        public int $uploadBytes,
    ) {}
}
