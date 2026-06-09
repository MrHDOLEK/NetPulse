<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRepository;
use App\Dashboard\Application\ReadModel\ServerMetricsRow;
use App\Dashboard\Application\ReadModel\ServerMetricsRowCollection;
use App\Measurement\Domain\Entity\Measurement;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ServerMetricsRepository::class, public: true)]
final readonly class SqlServerMetricsRepository implements ServerMetricsRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {}

    public function all(HeatmapWindow $window): ServerMetricsRowCollection
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone("UTC"));
        $since = $now->modify("-{$window->windowSeconds()} seconds");

        /**
         * @var list<array{
         *     serverId: string,
         *     name: string|null,
         *     location: string|null,
         *     avgDl: numeric-string|null,
         *     avgUp: numeric-string|null,
         *     avgPingMs: numeric-string|null,
         *     avgLoss: numeric-string|null,
         *     tests: int|string,
         *     healthy: int|string,
         *     lastSeen: string
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.serverId AS serverId",
                "MAX(measurement.serverName) AS name",
                "MAX(measurement.serverLocation) AS location",
                "AVG(measurement.downloadBits) AS avgDl",
                "AVG(measurement.uploadBits) AS avgUp",
                "AVG(measurement.ping) AS avgPingMs",
                "AVG(measurement.packetLossRatio) AS avgLoss",
                "COUNT(measurement.id) AS tests",
                "SUM(CASE WHEN measurement.healthy = :healthy THEN 1 ELSE 0 END) AS healthy",
                "MAX(measurement.completedAt) AS lastSeen",
            )
            ->from(Measurement::class, "measurement")
            ->where("measurement.serverId <> :empty")
            ->andWhere("measurement.completedAt >= :since")
            ->groupBy("measurement.serverId")
            ->orderBy("name", "ASC")
            ->setParameter("healthy", true, Types::BOOLEAN)
            ->setParameter("empty", "")
            ->setParameter("since", $since, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($rows as $row) {
            $items[] = new ServerMetricsRow(
                serverId: $row["serverId"],
                name: $row["name"] ?? "",
                location: $row["location"] ?? "",
                avgDownloadBits: $row["avgDl"] === null ? null : (float)$row["avgDl"],
                avgUploadBits: $row["avgUp"] === null ? null : (float)$row["avgUp"],
                avgPingSeconds: $row["avgPingMs"] === null ? null : ((float)$row["avgPingMs"]) / 1000.0,
                avgLossRatio: $row["avgLoss"] === null ? null : (float)$row["avgLoss"],
                testCount: (int)$row["tests"],
                healthyCount: (int)$row["healthy"],
                lastSeenUnix: new DateTimeImmutable($row["lastSeen"], new DateTimeZone("UTC"))->getTimestamp(),
            );
        }

        return ServerMetricsRowCollection::fromList($items);
    }
}
