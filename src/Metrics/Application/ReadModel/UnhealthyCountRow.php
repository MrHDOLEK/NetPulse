<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class UnhealthyCountRow
{
    public function __construct(
        public string $probeName,
        public string $connectionName,
        public int $count,
    ) {}
}
