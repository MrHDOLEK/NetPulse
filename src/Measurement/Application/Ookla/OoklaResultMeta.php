<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

final readonly class OoklaResultMeta
{
    public function __construct(
        public ?string $id = null,
        public ?string $url = null,
    ) {}
}
