<?php

declare(strict_types=1);

namespace App\Connection\Domain\ValueObject;

use App\Connection\Domain\Exception\InvalidExpectedSpeed;

final readonly class ExpectedSpeed
{
    public function __construct(
        public int $expectedDownloadBits,
        public int $expectedUploadBits,
    ) {
        if ($this->expectedDownloadBits < 0) {
            throw new InvalidExpectedSpeed("Expected download bits must not be negative.");
        }

        if ($this->expectedUploadBits < 0) {
            throw new InvalidExpectedSpeed("Expected upload bits must not be negative.");
        }
    }
}
