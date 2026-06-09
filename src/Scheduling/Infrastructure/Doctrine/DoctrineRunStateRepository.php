<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\RunStateRepository;
use App\Scheduling\Domain\ValueObject\RunPhase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: RunStateRepository::class)]
final readonly class DoctrineRunStateRepository implements RunStateRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function upsert(ConnectionId $connectionId, RunPhase $phase, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            "INSERT INTO run_states (connection_id, phase, updated_at) "
            . "VALUES (:connectionId, :phase, :updatedAt) "
            . "ON CONFLICT (connection_id) DO UPDATE SET "
            . "phase = excluded.phase, updated_at = excluded.updated_at",
            [
                "connectionId" => $connectionId->toString(),
                "phase" => $phase->value,
                "updatedAt" => $at->format("Y-m-d H:i:s"),
            ],
        );
    }

    public function markDoneIfPending(ConnectionId $connectionId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            "UPDATE run_states SET phase = :phase, updated_at = :updatedAt WHERE connection_id = :connectionId",
            [
                "phase" => RunPhase::Done->value,
                "updatedAt" => $at->format("Y-m-d H:i:s"),
                "connectionId" => $connectionId->toString(),
            ],
        );
    }
}
