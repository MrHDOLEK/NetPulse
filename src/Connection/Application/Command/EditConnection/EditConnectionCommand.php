<?php

declare(strict_types=1);

namespace App\Connection\Application\Command\EditConnection;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;

final readonly class EditConnectionCommand
{
    public function __construct(
        public ConnectionId $connectionId,
        public ProbeId $probeId,
        public string $name,
        public string $isp,
        public ExpectedSpeed $expected,
        public ConnectionColor $color,
        public Labels $labels,
        public ServerPool $serverPool,
        public Schedule $schedule,
        public Thresholds $thresholds,
        public AdaptivePolicy $adaptivePolicy,
    ) {}
}
