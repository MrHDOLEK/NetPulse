<?php

declare(strict_types=1);

namespace App\Dashboard\Application;

final readonly class OoklaServer
{
    public function __construct(
        public int $id,
        public string $name,
        public string $location,
        public string $host,
    ) {}
}
