<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

final readonly class DueWork
{
    public function __construct(
        public DueTaskCollection $tasks,
        public int $pollAfterSeconds,
    ) {}
}
