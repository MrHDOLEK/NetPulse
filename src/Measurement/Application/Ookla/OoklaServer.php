<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

final readonly class OoklaServer
{
    public function __construct(
        public int|string|null $id = null,
        public ?string $name = null,
        public ?string $location = null,
        public ?string $host = null,
        public int|string|null $port = null,
    ) {}
}
