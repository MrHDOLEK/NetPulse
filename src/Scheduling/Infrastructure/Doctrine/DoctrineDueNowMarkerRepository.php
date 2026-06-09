<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Domain\DueNowMarkerRepository;
use App\Scheduling\Domain\MarkedConnectionCollection;
use App\Scheduling\Domain\ValueObject\MarkedConnection;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_map;

#[AsAlias(id: DueNowMarkerRepository::class)]
final readonly class DoctrineDueNowMarkerRepository implements DueNowMarkerRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function mark(ConnectionId $connectionId, DateTimeImmutable $requestedAt, ?string $forcedServerId): void
    {
        $this->connection->executeStatement(
            "INSERT INTO due_now_markers (connection_id, requested_at, forced_server_id) "
            . "VALUES (:connectionId, :requestedAt, :forcedServerId) "
            . "ON CONFLICT (connection_id) DO UPDATE SET "
            . "requested_at = excluded.requested_at, forced_server_id = excluded.forced_server_id",
            [
                "connectionId" => $connectionId->toString(),
                "requestedAt" => $requestedAt->format("Y-m-d H:i:s"),
                "forcedServerId" => $forcedServerId,
            ],
        );
    }

    public function pullForProbe(ProbeId $probeId): MarkedConnectionCollection
    {
        return $this->connection->transactional(
            function (Connection $connection) use ($probeId): MarkedConnectionCollection {
                /** @var list<array{connection_id: string, forced_server_id: ?string}> $rows */
                $rows = $connection->createQueryBuilder()
                    ->select("marker.connection_id", "marker.forced_server_id")
                    ->from("due_now_markers", "marker")
                    ->innerJoin("marker", "connections", "connection", "connection.id = marker.connection_id")
                    ->where("connection.probe_id = :probeId")
                    ->setParameter("probeId", $probeId->toString())
                    ->executeQuery()
                    ->fetchAllAssociative();

                if ($rows === []) {
                    return MarkedConnectionCollection::fromList([]);
                }

                $ids = array_map(static fn(array $row): string => $row["connection_id"], $rows);

                $connection->createQueryBuilder()
                    ->delete("due_now_markers")
                    ->where("connection_id IN (:ids)")
                    ->setParameter("ids", $ids, ArrayParameterType::STRING)
                    ->executeStatement();

                return MarkedConnectionCollection::fromList(
                    array_map(
                        static fn(array $row): MarkedConnection => new MarkedConnection(
                            new ConnectionId($row["connection_id"]),
                            $row["forced_server_id"],
                        ),
                        $rows,
                    ),
                );
            },
        );
    }
}
