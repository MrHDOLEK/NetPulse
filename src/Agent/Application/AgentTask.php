<?php

declare(strict_types=1);

namespace App\Agent\Application;

final readonly class AgentTask
{
    public function __construct(
        public string $connectionId,
        public ?string $serverId,
    ) {}
}
