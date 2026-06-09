<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class PendingRun
{
    public function __construct(
        public string $connectionId,
        public string $connectionName,
        public string $color,
        public string $phase,
        public int $sinceUnix,
    ) {}
}
