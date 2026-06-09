<?php

declare(strict_types=1);

namespace App\Agent\Application;

final readonly class DuePlan
{
    /**
     * @param list<AgentTask> $tasks
     */
    public function __construct(
        public array $tasks,
        public int $pollAfterSeconds,
    ) {}
}
