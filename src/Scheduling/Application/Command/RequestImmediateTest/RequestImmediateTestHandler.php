<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\RequestImmediateTest;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Scheduling\Domain\RunStateRepository;
use App\Scheduling\Domain\ValueObject\RunPhase;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "command.bus")]
final readonly class RequestImmediateTestHandler
{
    private const string SCOPE_ALL = "all";

    public function __construct(
        private ConnectionRepository $connections,
        private DueNowMarkerRepository $markers,
        private RunStateRepository $runStates,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RequestImmediateTestCommand $command): void
    {
        $now = $this->clock->now();

        if ($command->scope === self::SCOPE_ALL) {
            $this->markAllEnabled($now);

            return;
        }

        $pin = ($command->forcedServerId === null || $command->forcedServerId === "")
            ? null
            : $command->forcedServerId;

        $connectionId = new ConnectionId((string)$command->connectionId);

        $this->connections->get($connectionId);

        $this->markers->mark($connectionId, $now, $pin);

        $this->runStates->upsert($connectionId, RunPhase::Queued, $now);

        $this->logger->info("immediate test requested", [
            "connection" => $connectionId->toString(),
            "forcedServerId" => $pin,
        ]);
    }

    private function markAllEnabled(DateTimeImmutable $now): void
    {
        $count = 0;

        foreach ($this->connections->allEnabled() as $connection) {
            $this->markers->mark($connection->id(), $now, null);

            $this->runStates->upsert($connection->id(), RunPhase::Queued, $now);
            ++$count;
        }

        $this->logger->info("immediate test requested for all enabled connections", [
            "count" => $count,
        ]);
    }
}
