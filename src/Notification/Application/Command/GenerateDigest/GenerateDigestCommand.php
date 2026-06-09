<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\GenerateDigest;

final readonly class GenerateDigestCommand
{
    public function __construct(
        public string $period,
    ) {}
}
