<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Connection\Domain\Entity\Connection;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Notification\Application\Digest\ConnectionDigest;
use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Application\Digest\DigestRepository;
use App\Probe\Domain\Entity\Probe;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: DigestRepository::class, public: true)]
final readonly class SqlDigestRepository implements DigestRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function since(DateTimeImmutable $since): ConnectionDigestCollection
    {
        /**
         * @var list<array{
         *     probeName: string,
         *     connectionName: string,
         *     avgDownloadBits: numeric-string|null,
         *     avgUploadBits: numeric-string|null,
         *     avgPingMs: numeric-string|null,
         *     avgPacketLossRatio: numeric-string|null,
         *     healthyCount: numeric-string|int,
         *     testsCount: int|string,
         *     failuresCount: numeric-string|int
         * }> $rows
         */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'probe.name AS probeName',
                'connection.name AS connectionName',
                'AVG(measurement.downloadBits) AS avgDownloadBits',
                'AVG(measurement.uploadBits) AS avgUploadBits',
                'AVG(measurement.ping) AS avgPingMs',
                'AVG(measurement.packetLossRatio) AS avgPacketLossRatio',
                'SUM(CASE WHEN measurement.healthy = :healthy THEN 1 ELSE 0 END) AS healthyCount',
                'COUNT(measurement.id) AS testsCount',
                'SUM(CASE WHEN measurement.status = :failed THEN 1 ELSE 0 END) AS failuresCount',
            )
            ->from(Measurement::class, 'measurement')
            ->join(Connection::class, 'connection', Join::WITH, 'connection.id = measurement.connectionId')
            ->join(Probe::class, 'probe', Join::WITH, 'probe.id = measurement.probeId')
            ->where('measurement.completedAt >= :since')
            ->groupBy('measurement.connectionId')
            ->orderBy('connection.name', 'ASC')
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->setParameter('healthy', true, Types::BOOLEAN)
            ->setParameter('failed', MeasurementStatus::Failed->value)
            ->getQuery()
            ->getResult();

        $digests = [];

        foreach ($rows as $row) {
            $testsCount = (int) $row['testsCount'];
            $healthyCount = (int) $row['healthyCount'];

            $digests[] = new ConnectionDigest(
                probeName: $row['probeName'],
                connectionName: $row['connectionName'],
                avgDownloadBits: (int) round((float) ($row['avgDownloadBits'] ?? 0)),
                avgUploadBits: (int) round((float) ($row['avgUploadBits'] ?? 0)),
                avgPingMs: (float) ($row['avgPingMs'] ?? 0),
                avgPacketLossRatio: (float) ($row['avgPacketLossRatio'] ?? 0),
                healthyRatio: $testsCount === 0 ? 0.0 : $healthyCount / $testsCount,
                testsCount: $testsCount,
                failuresCount: (int) $row['failuresCount'],
            );
        }

        return ConnectionDigestCollection::fromList($digests);
    }
}
