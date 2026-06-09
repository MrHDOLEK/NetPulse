<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Doctrine;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Metrics\Application\MetricsRepository;
use App\Metrics\Application\ReadModel\ConnectionWindow;
use App\Metrics\Application\ReadModel\ConnectionWindowCollection;
use App\Metrics\Application\ReadModel\DegradedRow;
use App\Metrics\Application\ReadModel\DegradedRowCollection;
use App\Metrics\Application\ReadModel\ExpectedRow;
use App\Metrics\Application\ReadModel\ExpectedRowCollection;
use App\Metrics\Application\ReadModel\LatestMeasurementRow;
use App\Metrics\Application\ReadModel\LatestMeasurementRowCollection;
use App\Metrics\Application\ReadModel\NotificationSendRow;
use App\Metrics\Application\ReadModel\NotificationSendRowCollection;
use App\Metrics\Application\ReadModel\RunCountRow;
use App\Metrics\Application\ReadModel\RunCountRowCollection;
use App\Metrics\Application\ReadModel\UnhealthyCountRow;
use App\Metrics\Application\ReadModel\UnhealthyCountRowCollection;
use App\Metrics\Domain\Entity\RemoteWriteFailureCount;
use App\Notification\Domain\Entity\NotificationSendCount;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Domain\DegradationDecider;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_key_exists;
use function count;

#[AsAlias(id: MetricsRepository::class, public: true)]
final readonly class SqlMetricsRepository implements MetricsRepository
{
    private const int HEALTH_HISTORY_LIMIT = 6;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DegradationDecider $degradationDecider,
    ) {}

    public function latestPerConnection(): LatestMeasurementRowCollection
    {
        $maxCompletedPerConnection =
            "SELECT MAX(latest.completedAt) FROM " . Measurement::class . " latest "
            . "WHERE latest.connectionId = measurement.connectionId "
            . "AND latest.status = :completed";

        /**
         * @var list<array{
         *     probeId: ProbeId,
         *     probeName: string,
         *     probeLabels: Labels,
         *     connectionId: ConnectionId,
         *     connectionName: string,
         *     isp: string,
         *     serverId: string,
         *     serverName: string,
         *     serverLocation: string,
         *     status: MeasurementStatus,
         *     completedAt: DateTimeImmutable,
         *     downloadBits: int|null,
         *     uploadBits: int|null,
         *     ping: float|null,
         *     jitter: float|null,
         *     packetLossRatio: float|null,
         *     downloadLatencyIqm: float|null,
         *     uploadLatencyIqm: float|null,
         *     dataUsedDownload: int,
         *     dataUsedUpload: int,
         *     healthy: bool|null
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.probeId AS probeId",
                "probe.name AS probeName",
                "probe.labels AS probeLabels",
                "measurement.connectionId AS connectionId",
                "connection.name AS connectionName",
                "measurement.isp AS isp",
                "measurement.serverId AS serverId",
                "measurement.serverName AS serverName",
                "measurement.serverLocation AS serverLocation",
                "measurement.status AS status",
                "measurement.completedAt AS completedAt",
                "measurement.downloadBits AS downloadBits",
                "measurement.uploadBits AS uploadBits",
                "measurement.ping AS ping",
                "measurement.jitter AS jitter",
                "measurement.packetLossRatio AS packetLossRatio",
                "measurement.downloadLatencyIqm AS downloadLatencyIqm",
                "measurement.uploadLatencyIqm AS uploadLatencyIqm",
                "measurement.dataUsedDownload AS dataUsedDownload",
                "measurement.dataUsedUpload AS dataUsedUpload",
                "measurement.healthy AS healthy",
            )
            ->from(Measurement::class, "measurement")
            ->join(Connection::class, "connection", Join::WITH, "connection.id = measurement.connectionId")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = measurement.probeId")
            ->where("measurement.status = :completed")
            ->andWhere("measurement.completedAt = (" . $maxCompletedPerConnection . ")")
            ->groupBy("measurement.connectionId")
            ->orderBy("connection.name", "ASC")
            ->setParameter("completed", MeasurementStatus::Completed->value)
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[] = new LatestMeasurementRow(
                probeId: $row["probeId"]->toString(),
                probeName: $row["probeName"],
                connectionId: $row["connectionId"]->toString(),
                connectionName: $row["connectionName"],
                isp: $row["isp"],
                serverId: $row["serverId"],
                serverName: $row["serverName"],
                serverLocation: $row["serverLocation"],
                site: $row["probeLabels"]->get("site") ?? "",
                status: $row["status"]->value,
                completedAtUnix: $row["completedAt"]->getTimestamp(),
                downloadBits: $row["downloadBits"],
                uploadBits: $row["uploadBits"],
                pingSeconds: $this->msToSeconds($row["ping"]),
                jitterSeconds: $this->msToSeconds($row["jitter"]),
                packetLossRatio: $row["packetLossRatio"],
                downloadLatencyIqmSeconds: $this->msToSeconds($row["downloadLatencyIqm"]),
                uploadLatencyIqmSeconds: $this->msToSeconds($row["uploadLatencyIqm"]),
                dataUsedBytes: $row["dataUsedDownload"] + $row["dataUsedUpload"],
                healthy: $row["healthy"],
            );
        }

        return LatestMeasurementRowCollection::fromList($result);
    }

    public function runCounts(): RunCountRowCollection
    {
        /** @var list<array{probeId: ProbeId, probeName: string, connectionId: ConnectionId, connectionName: string, status: MeasurementStatus, count: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.probeId AS probeId",
                "probe.name AS probeName",
                "measurement.connectionId AS connectionId",
                "connection.name AS connectionName",
                "measurement.status AS status",
                "COUNT(measurement.id) AS count",
            )
            ->from(Measurement::class, "measurement")
            ->join(Connection::class, "connection", Join::WITH, "connection.id = measurement.connectionId")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = measurement.probeId")
            ->groupBy("measurement.probeId")
            ->addGroupBy("measurement.connectionId")
            ->addGroupBy("measurement.status")
            ->orderBy("connection.name", "ASC")
            ->addOrderBy("measurement.status", "ASC")
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[] = new RunCountRow(
                probeId: $row["probeId"]->toString(),
                probeName: $row["probeName"],
                connectionId: $row["connectionId"]->toString(),
                connectionName: $row["connectionName"],
                status: $row["status"]->value,
                count: $row["count"],
            );
        }

        return RunCountRowCollection::fromList($result);
    }

    public function connectionsExpected(): ExpectedRowCollection
    {
        /** @var list<array{connectionId: ConnectionId, connectionName: string, probeName: string, expectedDownloadBits: int, expectedUploadBits: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "connection.id AS connectionId",
                "connection.name AS connectionName",
                "probe.name AS probeName",
                "connection.expected.expectedDownloadBits AS expectedDownloadBits",
                "connection.expected.expectedUploadBits AS expectedUploadBits",
            )
            ->from(Connection::class, "connection")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = connection.probeId")
            ->orderBy("connection.name", "ASC")
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[] = new ExpectedRow(
                connectionId: $row["connectionId"]->toString(),
                connectionName: $row["connectionName"],
                probeName: $row["probeName"],
                expectedDownloadBits: $row["expectedDownloadBits"],
                expectedUploadBits: $row["expectedUploadBits"],
            );
        }

        return ExpectedRowCollection::fromList($result);
    }

    public function unhealthyCounts(): UnhealthyCountRowCollection
    {
        /** @var list<array{probeName: string, connectionName: string, count: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "probe.name AS probeName",
                "connection.name AS connectionName",
                "COUNT(measurement.id) AS count",
            )
            ->from(Measurement::class, "measurement")
            ->join(Connection::class, "connection", Join::WITH, "connection.id = measurement.connectionId")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = measurement.probeId")
            ->where("measurement.healthy = :unhealthy")
            ->groupBy("measurement.probeId")
            ->addGroupBy("measurement.connectionId")
            ->orderBy("connection.name", "ASC")
            ->setParameter("unhealthy", false, Types::BOOLEAN)
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[] = new UnhealthyCountRow(
                probeName: $row["probeName"],
                connectionName: $row["connectionName"],
                count: $row["count"],
            );
        }

        return UnhealthyCountRowCollection::fromList($result);
    }

    public function connectionDegraded(): DegradedRowCollection
    {
        $result = [];

        foreach ($this->connectionWindows() as $window) {
            $degraded = $this->degradationDecider->isDegraded($window->history, $window->policy);

            $result[] = new DegradedRow(
                probeName: $window->probeName,
                connectionName: $window->connectionName,
                degraded: $degraded,
            );
        }

        return DegradedRowCollection::fromList($result);
    }

    public function remoteWriteFailures(): int
    {
        $total = $this->entityManager->createQueryBuilder()
            ->select("counter.total")
            ->from(RemoteWriteFailureCount::class, "counter")
            ->where("counter.id = :id")
            ->setParameter("id", RemoteWriteFailureCount::SINGLETON_ID)
            ->getQuery()
            ->getOneOrNullResult();

        if (is_array($total) && isset($total["total"]) && is_int($total["total"])) {
            return $total["total"];
        }

        return 0;
    }

    public function notificationSends(): NotificationSendRowCollection
    {
        /** @var list<array{kind: string, channel: string, status: string, total: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "counter.kind AS kind",
                "counter.channel AS channel",
                "counter.status AS status",
                "counter.total AS total",
            )
            ->from(NotificationSendCount::class, "counter")
            ->orderBy("counter.kind", "ASC")
            ->addOrderBy("counter.channel", "ASC")
            ->addOrderBy("counter.status", "ASC")
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[] = new NotificationSendRow(
                kind: $row["kind"],
                channel: $row["channel"],
                status: $row["status"],
                total: $row["total"],
            );
        }

        return NotificationSendRowCollection::fromList($result);
    }

    private function connectionWindows(): ConnectionWindowCollection
    {
        /**
         * @var list<array{
         *     connectionId: ConnectionId,
         *     connectionName: string,
         *     probeName: string,
         *     completedAt: DateTimeImmutable,
         *     status: MeasurementStatus,
         *     healthy: bool|null,
         *     adaptivePolicy: AdaptivePolicy
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.connectionId AS connectionId",
                "connection.name AS connectionName",
                "probe.name AS probeName",
                "measurement.completedAt AS completedAt",
                "measurement.status AS status",
                "measurement.healthy AS healthy",
                "connection.adaptivePolicy AS adaptivePolicy",
            )
            ->from(Measurement::class, "measurement")
            ->join(Connection::class, "connection", Join::WITH, "connection.id = measurement.connectionId")
            ->join(Probe::class, "probe", Join::WITH, "probe.id = measurement.probeId")
            ->orderBy("measurement.connectionId", "ASC")
            ->addOrderBy("measurement.completedAt", "DESC")
            ->getQuery()
            ->getResult();

        /** @var array<string, ConnectionId> $connectionId */
        $connectionId = [];
        /** @var array<string, string> $connectionName */
        $connectionName = [];
        /** @var array<string, string> $probeName */
        $probeName = [];
        /** @var array<string, AdaptivePolicy> $policy */
        $policy = [];
        /** @var array<string, list<HealthSample>> $samples */
        $samples = [];

        foreach ($rows as $row) {
            $key = $row["connectionId"]->toString();

            if (!array_key_exists($key, $samples)) {
                $connectionId[$key] = $row["connectionId"];
                $connectionName[$key] = $row["connectionName"];
                $probeName[$key] = $row["probeName"];
                $policy[$key] = $row["adaptivePolicy"];
                $samples[$key] = [];
            }

            if (count($samples[$key]) >= self::HEALTH_HISTORY_LIMIT) {
                continue;
            }

            $samples[$key][] = $this->toSample($row["completedAt"], $row["status"], $row["healthy"]);
        }

        $windows = [];

        foreach ($samples as $key => $connectionSamples) {
            $windows[] = new ConnectionWindow(
                connectionId: $connectionId[$key],
                probeName: $probeName[$key],
                connectionName: $connectionName[$key],
                policy: $policy[$key],
                history: HealthHistory::fromList($connectionSamples),
            );
        }

        return ConnectionWindowCollection::fromList($windows);
    }

    private function toSample(DateTimeImmutable $completedAt, MeasurementStatus $status, ?bool $healthy): HealthSample
    {
        if ($status === MeasurementStatus::Failed) {
            return HealthSample::failed($completedAt);
        }

        return HealthSample::completed($completedAt, $healthy);
    }

    private function msToSeconds(?float $value): ?float
    {
        return $value === null ? null : $value / 1000.0;
    }
}
