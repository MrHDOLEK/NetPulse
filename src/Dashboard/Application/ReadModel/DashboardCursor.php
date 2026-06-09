<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class DashboardCursor
{
    public function __construct(
        public ?int $latestCompletedAtUnix,
        public int $totalCount,
    ) {}
}
