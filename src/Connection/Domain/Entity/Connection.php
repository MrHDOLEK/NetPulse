<?php

declare(strict_types=1);

namespace App\Connection\Domain\Entity;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;

class Connection
{
    public function __construct(
        private readonly ConnectionId $id,
        private readonly ProbeId $probeId,
        private string $name,
        private string $isp,
        private ExpectedSpeed $expected,
        private ConnectionColor $color,
        private Labels $labels,
        private ServerPool $serverPool,
        private Schedule $schedule,
        private bool $enabled,
        private Thresholds $thresholds,
        private AdaptivePolicy $adaptivePolicy,
    ) {}

    public function id(): ConnectionId
    {
        return $this->id;
    }

    public function probeId(): ProbeId
    {
        return $this->probeId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isp(): string
    {
        return $this->isp;
    }

    public function expected(): ExpectedSpeed
    {
        return $this->expected;
    }

    public function color(): ConnectionColor
    {
        return $this->color;
    }

    public function labels(): Labels
    {
        return $this->labels;
    }

    public function serverPool(): ServerPool
    {
        return $this->serverPool;
    }

    public function schedule(): Schedule
    {
        return $this->schedule;
    }

    public function thresholds(): Thresholds
    {
        return $this->thresholds;
    }

    public function adaptivePolicy(): AdaptivePolicy
    {
        return $this->adaptivePolicy;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function reconfigure(
        string $name,
        string $isp,
        ExpectedSpeed $expected,
        ConnectionColor $color,
        Labels $labels,
        ServerPool $serverPool,
        Schedule $schedule,
        Thresholds $thresholds,
        AdaptivePolicy $adaptivePolicy,
    ): void {
        $this->name = $name;
        $this->isp = $isp;
        $this->expected = $expected;
        $this->color = $color;
        $this->labels = $labels;
        $this->serverPool = $serverPool;
        $this->schedule = $schedule;
        $this->thresholds = $thresholds;
        $this->adaptivePolicy = $adaptivePolicy;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function belongsTo(ProbeId $probeId): bool
    {
        return $this->probeId->equals($probeId);
    }
}
