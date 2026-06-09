<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

final readonly class RunCountRow
{
    public function __construct(
        public string $probeId,
        public string $probeName,
        public string $connectionId,
        public string $connectionName,
        public string $status,
        public int $count,
    ) {}
}
