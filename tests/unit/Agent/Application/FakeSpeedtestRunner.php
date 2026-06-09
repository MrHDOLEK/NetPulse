<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent\Application;

use App\Agent\Application\SpeedtestOutcome;
use App\Agent\Application\SpeedtestRunner;
use RuntimeException;

use function array_shift;

final class FakeSpeedtestRunner implements SpeedtestRunner
{
    /** @var list<string|null> */
    public array $runForServerIds = [];

    /**
     * @param list<SpeedtestOutcome|'throw'> $outcomes one per expected run, in order
     */
    public function __construct(
        /** @var list<SpeedtestOutcome|'throw'> */
        private array $outcomes,
    ) {}

    public function run(?string $serverId): SpeedtestOutcome
    {
        $this->runForServerIds[] = $serverId;

        $next = array_shift($this->outcomes);

        if ($next === "throw") {
            throw new RuntimeException("runner exploded");
        }

        if ($next === null) {
            throw new RuntimeException("FakeSpeedtestRunner ran out of canned outcomes");
        }

        return $next;
    }
}
