<?php

declare(strict_types=1);

namespace App\Scheduling\Application\GetDueWork;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Application\DueTaskCollection;
use App\Scheduling\Application\DueWork;
use App\Scheduling\Application\LastMeasurementRepository;
use App\Scheduling\Application\LastMeasurementRowCollection;
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Scheduling\Domain\DueWorkCalculator;
use App\Scheduling\Domain\RunStateRepository;
use App\Scheduling\Domain\ValueObject\DueTask;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\RunPhase;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_values;
use function count;

final readonly class GetDueWorkHandler
{
    public function __construct(
        private ConnectionRepository $connections,
        private LastMeasurementRepository $lastMeasurements,
        private DueWorkCalculator $calculator,
        private DueNowMarkerRepository $markers,
        private RunStateRepository $runStates,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire("%env(int:AGENT_POLL_INTERVAL)%")]
        private int $pollAfterSeconds,
    ) {}

    public function __invoke(GetDueWorkQuery $query): DueWork
    {
        $now = $this->clock->now();
        $lastByConnection = $this->lastMeasurements->forProbe($query->probeId);

        /** @var array<string, Connection> $connectionsById */
        $connectionsById = [];
        /** @var array<string, DueTask> $tasksByConnection */
        $tasksByConnection = [];

        foreach ($this->connections->byProbe($query->probeId) as $connection) {
            $connectionsById[$connection->id()->toString()] = $connection;

            if (!$connection->isEnabled()) {
                continue;
            }

            $task = $this->decide($connection, $lastByConnection, $now);

            if ($task !== null) {
                $tasksByConnection[$connection->id()->toString()] = $task;
            }
        }

        $forced = $this->forceMarked($query->probeId, $connectionsById, $tasksByConnection, $lastByConnection, $now);

        $tasks = array_values($tasksByConnection);

        $this->logger->debug("due work computed", [
            "probe" => $query->probeId->toString(),
            "tasks" => count($tasks),
            "forced" => $forced,
        ]);

        return new DueWork(
            DueTaskCollection::fromList($tasks),
            $this->pollAfterSeconds,
        );
    }

    /**
     * @param array<string, Connection> $connectionsById
     * @param array<string, DueTask> $tasksByConnection mutated in place with forced tasks
     * @return int number of connections newly forced due
     */
    private function forceMarked(
        ProbeId $probeId,
        array $connectionsById,
        array &$tasksByConnection,
        LastMeasurementRowCollection $lastByConnection,
        DateTimeImmutable $now,
    ): int {
        $forced = 0;

        foreach ($this->markers->pullForProbe($probeId) as $marked) {
            $key = $marked->connectionId->toString();

            $connection = $connectionsById[$key] ?? null;

            if ($connection === null || !$connection->isEnabled()) {
                continue;
            }

            $this->runStates->upsert($connection->id(), RunPhase::Running, $now);

            if (isset($tasksByConnection[$key])) {
                continue;
            }

            if ($marked->forcedServerId !== null) {
                $tasksByConnection[$key] = new DueTask($connection->id(), $marked->forcedServerId);
            } else {
                $last = $lastByConnection->forConnection($key);
                $decision = $this->calculator->forcedDue($connection->serverPool(), $last?->serverId);

                $tasksByConnection[$key] = new DueTask($connection->id(), $decision->serverId);
            }

            $forced++;
        }

        return $forced;
    }

    private function decide(
        Connection $connection,
        LastMeasurementRowCollection $lastByConnection,
        DateTimeImmutable $now,
    ): ?DueTask {
        $last = $lastByConnection->forConnection($connection->id()->toString());

        $decision = $this->calculator->decide(
            $connection->schedule(),
            $connection->serverPool(),
            $last?->completedAt,
            $last?->serverId,
            $connection->id()->toString(),
            $now,
            $connection->adaptivePolicy(),
            $last === null ? HealthHistory::empty() : $last->healthHistory,
        );

        if ($decision === null) {
            return null;
        }

        return new DueTask($connection->id(), $decision->serverId);
    }
}
