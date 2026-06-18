<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListItem;
use App\Dashboard\Application\ReadModel\MeasurementListItemCollection;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: MeasurementListRepository::class, public: true)]
final readonly class SqlMeasurementListRepository implements MeasurementListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function list(
        MeasurementFilter $filter,
        int $limit,
        int $offset,
        MeasurementSort $sort,
    ): MeasurementListItemCollection {
        [$sortField, $sortDirection] = $sort->orderBy();

        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'measurement.id AS id',
                'measurement.completedAt AS completedAt',
                'measurement.status AS status',
                'connection.name AS connectionName',
                'connection.color AS connectionColor',
                'connection.isp AS isp',
                'measurement.serverName AS serverName',
                'measurement.serverLocation AS serverLocation',
                'measurement.downloadBits AS downloadBits',
                'measurement.uploadBits AS uploadBits',
                '(measurement.ping / 1000.0) AS pingSeconds',
                '(measurement.jitter / 1000.0) AS jitterSeconds',
                'measurement.packetLossRatio AS packetLossRatio',
                'measurement.healthy AS healthy',
                'measurement.scheduled AS scheduled',
            )
            ->from(Measurement::class, 'measurement')
            ->join(Connection::class, 'connection', Join::WITH, 'connection.id = measurement.connectionId')
            ->orderBy($sortField, $sortDirection)
            ->addOrderBy('measurement.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->applyFilter($queryBuilder, $filter);

        /**
         * @var list<array{
         *     id: MeasurementId,
         *     completedAt: DateTimeImmutable,
         *     status: MeasurementStatus,
         *     connectionName: string,
         *     connectionColor: ConnectionColor,
         *     isp: string,
         *     serverName: string,
         *     serverLocation: string,
         *     downloadBits: int|null,
         *     uploadBits: int|null,
         *     pingSeconds: float|null,
         *     jitterSeconds: float|null,
         *     packetLossRatio: float|null,
         *     healthy: bool|null,
         *     scheduled: bool
         * }> $rows
         */
        $rows = $queryBuilder->getQuery()->getResult();

        $items = [];

        foreach ($rows as $row) {
            $items[] = new MeasurementListItem(
                id: $row['id'],
                completedAtUnix: $row['completedAt']->getTimestamp(),
                status: $row['status'],
                connectionName: $row['connectionName'],
                connectionColor: $row['connectionColor'],
                isp: $row['isp'],
                serverName: $row['serverName'],
                serverLocation: $row['serverLocation'],
                downloadBits: $row['downloadBits'],
                uploadBits: $row['uploadBits'],
                pingSeconds: $row['pingSeconds'],
                jitterSeconds: $row['jitterSeconds'],
                packetLossRatio: $row['packetLossRatio'],
                healthy: $row['healthy'],
                scheduled: $row['scheduled'],
            );
        }

        return MeasurementListItemCollection::fromList($items);
    }

    public function countMatching(MeasurementFilter $filter): int
    {
        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(measurement.id)')
            ->from(Measurement::class, 'measurement');

        $this->applyFilter($queryBuilder, $filter);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function applyFilter(QueryBuilder $queryBuilder, MeasurementFilter $filter): void
    {
        $queryBuilder
            ->where('measurement.completedAt >= :since')
            ->andWhere('measurement.completedAt < :until')
            ->setParameter('since', $filter->since, Types::DATETIME_IMMUTABLE)
            ->setParameter('until', $filter->until, Types::DATETIME_IMMUTABLE);

        if ($filter->connection !== null) {
            $queryBuilder->andWhere('measurement.connectionId = :connection')->setParameter(
                'connection',
                $filter->connection,
                'connection_id',
            );
        }

        if ($filter->serverId !== null) {
            $queryBuilder->andWhere('measurement.serverId = :serverId')->setParameter('serverId', $filter->serverId);
        }

        if ($filter->status !== null) {
            $queryBuilder->andWhere('measurement.status = :status')->setParameter('status', $filter->status->value);
        }

        if ($filter->healthy !== null) {
            $queryBuilder->andWhere('measurement.healthy = :healthy')->setParameter(
                'healthy',
                $filter->healthy,
                Types::BOOLEAN,
            );
        }

        if ($filter->scheduled !== null) {
            $queryBuilder->andWhere('measurement.scheduled = :scheduled')->setParameter(
                'scheduled',
                $filter->scheduled,
                Types::BOOLEAN,
            );
        }
    }
}
