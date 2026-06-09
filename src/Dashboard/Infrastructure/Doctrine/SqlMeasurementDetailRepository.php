<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Dashboard\Application\ReadModel\MeasurementDetail;
use App\Dashboard\Application\ReadModel\MeasurementDetailRepository;
use App\Dashboard\Application\ReadModel\MeasurementNotFound;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\MeasurementId;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: MeasurementDetailRepository::class, public: true)]
final readonly class SqlMeasurementDetailRepository implements MeasurementDetailRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function get(MeasurementId $id): MeasurementDetail
    {
        /**
         * @var array{
         *     id: MeasurementId,
         *     completedAt: DateTimeImmutable,
         *     startedAt: DateTimeImmutable,
         *     connectionName: string,
         *     connectionColor: ConnectionColor,
         *     isp: string,
         *     serverId: string,
         *     serverName: string,
         *     serverLocation: string,
         *     serverHost: string,
         *     scheduled: bool,
         *     status: MeasurementStatus,
         *     downloadBits: int|null,
         *     uploadBits: int|null,
         *     pingSeconds: float|null,
         *     pingLowSeconds: float|null,
         *     pingHighSeconds: float|null,
         *     jitterSeconds: float|null,
         *     downloadLatencyIqmSeconds: float|null,
         *     uploadLatencyIqmSeconds: float|null,
         *     packetLossRatio: float|null,
         *     healthy: bool|null,
         *     dataUsedDownload: int|null,
         *     dataUsedUpload: int|null,
         *     resultUrl: string|null,
         *     rawPayload: array<string,mixed>
         * }|null $row
         */
        $row = $this->fetchRow($id);

        if ($row === null) {
            throw MeasurementNotFound::withId($id);
        }

        return new MeasurementDetail(
            id: $row["id"],
            completedAtUnix: $row["completedAt"]->getTimestamp(),
            startedAtUnix: $row["startedAt"]->getTimestamp(),
            connectionName: $row["connectionName"],
            connectionColor: $row["connectionColor"],
            isp: $row["isp"],
            serverId: $row["serverId"],
            serverName: $row["serverName"],
            serverLocation: $row["serverLocation"],
            serverHost: $row["serverHost"],
            scheduled: $row["scheduled"],
            status: $row["status"],
            failReason: $this->failReason($row["rawPayload"]),
            downloadBits: $row["downloadBits"],
            uploadBits: $row["uploadBits"],
            pingSeconds: $row["pingSeconds"],
            pingLowSeconds: $row["pingLowSeconds"],
            pingHighSeconds: $row["pingHighSeconds"],
            jitterSeconds: $row["jitterSeconds"],
            downloadLatencyIqmSeconds: $row["downloadLatencyIqmSeconds"],
            uploadLatencyIqmSeconds: $row["uploadLatencyIqmSeconds"],
            packetLossRatio: $row["packetLossRatio"],
            healthy: $row["healthy"],
            dataUsedDownload: $row["dataUsedDownload"],
            dataUsedUpload: $row["dataUsedUpload"],
            resultUrl: $row["resultUrl"],
            rawPayload: $row["rawPayload"],
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchRow(MeasurementId $id): ?array
    {
        /** @var array<string,mixed>|null $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select(
                "measurement.id AS id",
                "measurement.completedAt AS completedAt",
                "measurement.startedAt AS startedAt",
                "connection.name AS connectionName",
                "connection.color AS connectionColor",
                "connection.isp AS isp",
                "measurement.serverId AS serverId",
                "measurement.serverName AS serverName",
                "measurement.serverLocation AS serverLocation",
                "measurement.serverHost AS serverHost",
                "measurement.scheduled AS scheduled",
                "measurement.status AS status",
                "measurement.downloadBits AS downloadBits",
                "measurement.uploadBits AS uploadBits",
                "(measurement.ping / 1000.0) AS pingSeconds",
                "(measurement.pingLow / 1000.0) AS pingLowSeconds",
                "(measurement.pingHigh / 1000.0) AS pingHighSeconds",
                "(measurement.jitter / 1000.0) AS jitterSeconds",
                "(measurement.downloadLatencyIqm / 1000.0) AS downloadLatencyIqmSeconds",
                "(measurement.uploadLatencyIqm / 1000.0) AS uploadLatencyIqmSeconds",
                "measurement.packetLossRatio AS packetLossRatio",
                "measurement.healthy AS healthy",
                "measurement.dataUsedDownload AS dataUsedDownload",
                "measurement.dataUsedUpload AS dataUsedUpload",
                "measurement.resultUrl AS resultUrl",
                "measurement.rawPayload AS rawPayload",
            )
            ->from(Measurement::class, "measurement")
            ->join(Connection::class, "connection", Join::WITH, "connection.id = measurement.connectionId")
            ->where("measurement.id = :id")
            ->setParameter("id", $id, "measurement_id")
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    /**
     * @param array<string,mixed> $rawPayload
     */
    private function failReason(array $rawPayload): ?string
    {
        $reason = $rawPayload["error"] ?? $rawPayload["message"] ?? null;

        if (is_string($reason) && $reason !== "") {
            return $reason;
        }

        return null;
    }
}
