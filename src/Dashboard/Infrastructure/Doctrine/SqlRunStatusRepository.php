<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\RunStatus;
use App\Dashboard\Application\ReadModel\RunStatusRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: RunStatusRepository::class, public: true)]
final readonly class SqlRunStatusRepository implements RunStatusRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function forConnection(ConnectionId $connectionId): RunStatus
    {
        /** @var array{phase: string, updated_at: string}|false $row */
        $row = $this->connection->createQueryBuilder()
            ->select("run_state.phase", "run_state.updated_at")
            ->from("run_states", "run_state")
            ->where("run_state.connection_id = :connectionId")
            ->setParameter("connectionId", $connectionId->toString())
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return RunStatus::idle();
        }

        $updatedAt = new DateTimeImmutable($row["updated_at"], new DateTimeZone("UTC"));

        return new RunStatus($row["phase"], $updatedAt->getTimestamp());
    }
}
