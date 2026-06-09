<?php

declare(strict_types=1);

namespace App\Agent\Application;

final readonly class TickSummary
{
    public function __construct(
        public int $tasks,
        public int $succeeded,
        public int $failed,
        public int $errored,
        public int $pollAfterSeconds,
    ) {}
}
