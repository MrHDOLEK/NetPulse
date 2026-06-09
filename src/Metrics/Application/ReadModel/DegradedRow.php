<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class DegradedRow
{
    public function __construct(
        public string $probeName,
        public string $connectionName,
        public bool $degraded,
    ) {}
}
