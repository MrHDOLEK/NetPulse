<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\PendingRun;
use App\Dashboard\Application\ReadModel\PendingRunsRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: PendingRunsRepository::class, public: true)]
final readonly class SqlPendingRunsRepository implements PendingRunsRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function pending(): array
    {
        /** @var list<array{connectionId: string, name: string, color: string, phase: string, updatedAt: string}> $rows */
        $rows = $this->connection->createQueryBuilder()
            ->select(
                "run_state.connection_id AS connectionId",
                "connection.name AS name",
                "connection.color AS color",
                "run_state.phase AS phase",
                "run_state.updated_at AS updatedAt",
            )
            ->from("run_states", "run_state")
            ->innerJoin("run_state", "connections", "connection", "connection.id = run_state.connection_id")
            ->where("run_state.phase != :done")
            ->setParameter("done", "done")
            ->orderBy("run_state.updated_at", "DESC")
            ->executeQuery()
            ->fetchAllAssociative();

        $pending = [];

        foreach ($rows as $row) {
            $since = new DateTimeImmutable($row["updatedAt"], new DateTimeZone("UTC"));

            $pending[] = new PendingRun(
                $row["connectionId"],
                $row["name"],
                $row["color"],
                $row["phase"],
                $since->getTimestamp(),
            );
        }

        return $pending;
    }
}
