<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\ServerListItem;
use App\Dashboard\Application\ReadModel\ServerListItemCollection;
use App\Dashboard\Application\ReadModel\ServerListRepository;
use App\Measurement\Domain\Entity\Measurement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ServerListRepository::class, public: true)]
final readonly class SqlServerListRepository implements ServerListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function all(): ServerListItemCollection
    {
        /** @var list<array{serverId: string, name: string|null, location: string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.serverId AS serverId",
                "MAX(measurement.serverName) AS name",
                "MAX(measurement.serverLocation) AS location",
            )
            ->from(Measurement::class, "measurement")
            ->where("measurement.serverId <> :empty")
            ->setParameter("empty", "")
            ->groupBy("measurement.serverId")
            ->orderBy("name", "ASC")
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($rows as $row) {
            $items[] = new ServerListItem(
                serverId: $row["serverId"],
                name: $row["name"] ?? "",
                location: $row["location"] ?? "",
            );
        }

        return ServerListItemCollection::fromList($items);
    }
}
