<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Request;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Shared\Domain\ValueObject\Labels;

final readonly class ConnectionDraft
{
    public function __construct(
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
