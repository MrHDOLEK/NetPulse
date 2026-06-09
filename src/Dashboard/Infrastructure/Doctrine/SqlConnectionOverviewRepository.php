<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\ConnectionListItem;
use App\Dashboard\Application\ReadModel\ConnectionListRepository;
use App\Dashboard\Application\ReadModel\ConnectionOverview;
use App\Dashboard\Application\ReadModel\ConnectionOverviewCollection;
use App\Dashboard\Application\ReadModel\ConnectionOverviewRepository;
use App\Dashboard\Application\ReadModel\Enum\ConnectionStatus;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Metrics\Application\MetricsRepository;
use App\Metrics\Application\ReadModel\LatestMeasurementRow;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_key_exists;

#[AsAlias(id: ConnectionOverviewRepository::class, public: true)]
final readonly class SqlConnectionOverviewRepository implements ConnectionOverviewRepository
{
    public function __construct(
        private ConnectionListRepository $connectionList,
        private MetricsRepository $metrics,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {}

    public function overview(SeriesRange $range): ConnectionOverviewCollection
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone("UTC"));
        $since = $now->modify("-{$range->windowSeconds()} seconds");

        $latest = $this->latestByConnectionId();
        $degraded = $this->degradedByListKey();
        $aggregates = $this->windowAggregates($since);

        $overviews = [];

        foreach ($this->connectionList->all() as $connection) {
            $connectionKey = $connection->connectionId->toString();
            $aggregate = $aggregates[$connectionKey] ?? WindowAggregate::empty();
            $latestRow = $latest[$connectionKey] ?? null;

            $overviews[] = new ConnectionOverview(
                connectionId: $connection->connectionId,
                name: $connection->name,
                color: $connection->color,
                isp: $connection->isp,
                downloadBits: $latestRow?->downloadBits,
                uploadBits: $latestRow?->uploadBits,
                pingSeconds: $latestRow?->pingSeconds,
                jitterSeconds: $latestRow?->jitterSeconds,
                packetLossRatio: $latestRow?->packetLossRatio,
                completedAtUnix: $latestRow?->completedAtUnix,
                serverName: $latestRow->serverName ?? "",
                serverLocation: $latestRow->serverLocation ?? "",
                latestHealthy: $latestRow?->healthy,
                status: $this->deriveStatus($connection, $latestRow, $aggregate, $degraded),
                testsRun: $aggregate->testsRun,
                incidents: $aggregate->incidents,
                uptimePct: $aggregate->uptimePct(),
            );
        }

        return ConnectionOverviewCollection::fromList($overviews);
    }

    private function deriveStatus(
        ConnectionListItem $connection,
        ?LatestMeasurementRow $latestRow,
        WindowAggregate $aggregate,
        ConnectionDegradedIndex $degraded,
    ): ConnectionStatus {
        if ($aggregate->latestFailed || $latestRow === null) {
            return ConnectionStatus::Down;
        }

        if ($degraded->isDegraded($connection->probeName, $connection->name)) {
            return ConnectionStatus::Degraded;
        }

        return ConnectionStatus::Healthy;
    }

    /**
     * @return array<string, LatestMeasurementRow>
     */
    private function latestByConnectionId(): array
    {
        $byConnectionId = [];

        foreach ($this->metrics->latestPerConnection() as $row) {
            $byConnectionId[$row->connectionId] = $row;
        }

        return $byConnectionId;
    }

    private function degradedByListKey(): ConnectionDegradedIndex
    {
        $degraded = [];

        foreach ($this->metrics->connectionDegraded() as $row) {
            $degraded[ConnectionDegradedIndex::key($row->probeName, $row->connectionName)] = $row->degraded;
        }

        return new ConnectionDegradedIndex($degraded);
    }

    /**
     * @return array<string, WindowAggregate>
     */
    private function windowAggregates(DateTimeImmutable $since): array
    {
        /**
         * @var list<array{
         *     connectionId: ConnectionId,
         *     status: MeasurementStatus,
         *     healthy: bool|null
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.connectionId AS connectionId",
                "measurement.status AS status",
                "measurement.healthy AS healthy",
            )
            ->from(Measurement::class, "measurement")
            ->where("measurement.completedAt >= :since")
            ->orderBy("measurement.connectionId", "ASC")
            ->addOrderBy("measurement.completedAt", "DESC")
            ->setParameter("since", $since, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        /** @var array<string, WindowAggregate> $aggregates */
        $aggregates = [];

        foreach ($rows as $row) {
            $key = $row["connectionId"]->toString();
            $failed = $row["status"] === MeasurementStatus::Failed;
            $unhealthy = $row["healthy"] === false;
            $healthy = $row["healthy"] === true;

            if (!array_key_exists($key, $aggregates)) {
                $aggregates[$key] = WindowAggregate::first($failed);
            }

            $aggregates[$key] = $aggregates[$key]->add(
                isFailed: $failed,
                isIncident: $failed || $unhealthy,
                isHealthy: $healthy,
            );
        }

        return $aggregates;
    }
}
