<?php

declare(strict_types=1);

namespace App\Metrics\Application\ReadModel;

use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\HealthHistory;

final readonly class ConnectionWindow
{
    public function __construct(
        public ConnectionId $connectionId,
        public string $probeName,
        public string $connectionName,
        public AdaptivePolicy $policy,
        public HealthHistory $history,
    ) {}
}
