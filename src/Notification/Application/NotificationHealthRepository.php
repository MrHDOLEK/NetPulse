<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\HealthHistory;

interface NotificationHealthRepository
{
    public function forConnection(ConnectionId $connectionId, int $limit): HealthHistory;
}
