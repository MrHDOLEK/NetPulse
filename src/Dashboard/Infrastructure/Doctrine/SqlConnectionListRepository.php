<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\ConnectionListItem;
use App\Dashboard\Application\ReadModel\ConnectionListItemCollection;
use App\Dashboard\Application\ReadModel\ConnectionListRepository;
use App\Probe\Domain\Entity\Probe;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ConnectionListRepository::class, public: true)]
final readonly class SqlConnectionListRepository implements ConnectionListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function all(): ConnectionListItemCollection
    {
        /**
         * @var list<array{
         *     connectionId: ConnectionId,
         *     name: string,
         *     isp: string,
         *     color: ConnectionColor,
         *     expectedDownloadBits: int,
         *     probeName: string
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "connection.id AS connectionId",
                "connection.name AS name",
                "connection.isp AS isp",
                "connection.color AS color",
                "connection.expected.expectedDownloadBits AS expectedDownloadBits",
                "probe.name AS probeName",
            )
            ->from(Connection::class, "connection")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = connection.probeId")
            ->orderBy("probe.name", "ASC")
            ->addOrderBy("connection.name", "ASC")
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($rows as $row) {
            $items[] = new ConnectionListItem(
                connectionId: $row["connectionId"],
                name: $row["name"],
                isp: $row["isp"],
                color: $row["color"],
                expectedDownloadBits: $row["expectedDownloadBits"],
                probeName: $row["probeName"],
            );
        }

        return ConnectionListItemCollection::fromList($items);
    }
}
