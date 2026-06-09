<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\ValueObject\HealthHistory;

interface RecentHealthRepository
{
    public function recent(ConnectionId $id, int $limit = 60): HealthHistory;
}
