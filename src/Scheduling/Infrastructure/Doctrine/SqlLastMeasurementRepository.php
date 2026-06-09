<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Application\LastMeasurementRepository;
use App\Scheduling\Application\LastMeasurementRow;
use App\Scheduling\Application\LastMeasurementRowCollection;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_key_exists;
use function count;

#[AsAlias(id: LastMeasurementRepository::class, public: true)]
final readonly class SqlLastMeasurementRepository implements LastMeasurementRepository
{
    private const int HEALTH_HISTORY_LIMIT = 6;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function forProbe(ProbeId $probeId): LastMeasurementRowCollection
    {
        /**
         * @var list<array{
         *     connectionId: ConnectionId,
         *     completedAt: DateTimeImmutable,
         *     serverId: string,
         *     status: MeasurementStatus,
         *     healthy: bool|null
         * }> $rows
         */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                "measurement.connectionId AS connectionId",
                "measurement.completedAt AS completedAt",
                "measurement.serverId AS serverId",
                "measurement.status AS status",
                "measurement.healthy AS healthy",
            )
            ->from(Measurement::class, "measurement")
            ->where("measurement.probeId = :probeId")
            ->setParameter("probeId", $probeId, "probe_id")
            ->orderBy("measurement.connectionId", "ASC")
            ->addOrderBy("measurement.completedAt", "DESC")
            ->getQuery()
            ->getResult();

        return LastMeasurementRowCollection::fromList($this->toRows($rows));
    }

    /**
     * @param list<array{
     *     connectionId: ConnectionId,
     *     completedAt: DateTimeImmutable,
     *     serverId: string,
     *     status: MeasurementStatus,
     *     healthy: bool|null
     * }> $rows
     * @return list<LastMeasurementRow>
     */
    private function toRows(array $rows): array
    {
        /** @var array<string, DateTimeImmutable> $latestAt */
        $latestAt = [];
        /** @var array<string, string> $latestServer */
        $latestServer = [];
        /** @var array<string, ConnectionId> $connectionId */
        $connectionId = [];
        /** @var array<string, list<HealthSample>> $samples */
        $samples = [];

        foreach ($rows as $row) {
            $key = $row["connectionId"]->toString();

            if (!array_key_exists($key, $latestAt)) {
                $latestAt[$key] = $row["completedAt"];
                $latestServer[$key] = $row["serverId"];
                $connectionId[$key] = $row["connectionId"];
                $samples[$key] = [];
            }

            if (count($samples[$key]) >= self::HEALTH_HISTORY_LIMIT) {
                continue;
            }

            $samples[$key][] = $this->toSample($row["completedAt"], $row["status"], $row["healthy"]);
        }

        $result = [];

        foreach ($connectionId as $key => $id) {
            $result[] = new LastMeasurementRow(
                $id,
                $latestAt[$key],
                $latestServer[$key],
                HealthHistory::fromList($samples[$key]),
            );
        }

        return $result;
    }

    private function toSample(DateTimeImmutable $completedAt, MeasurementStatus $status, ?bool $healthy): HealthSample
    {
        if ($status === MeasurementStatus::Failed) {
            return HealthSample::failed($completedAt);
        }

        return HealthSample::completed($completedAt, $healthy);
    }
}
