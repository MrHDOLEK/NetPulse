<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Bucketing\Bucketer;
use App\Dashboard\Application\ReadModel\Bucketing\MeasurementSample;
use App\Dashboard\Application\ReadModel\Bucketing\MeasurementSampleCollection;
use App\Dashboard\Application\ReadModel\ConnectionSeriesRepository;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\SeriesBucketCollection;
use App\Measurement\Domain\Entity\Measurement;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ConnectionSeriesRepository::class, public: true)]
final readonly class SqlConnectionSeriesRepository implements ConnectionSeriesRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private Bucketer $bucketer,
    ) {}

    public function series(ConnectionId $id, SeriesRange $range, SeriesMetric $metric): SeriesBucketCollection
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $windowSeconds = $range->windowSeconds();
        $since = $now->modify("-{$windowSeconds} seconds");
        $prevSince = $since->modify("-{$windowSeconds} seconds");

        $samples = $this->fetchSamples($id, $metric, $prevSince, $now);

        return $this->bucketer->build(
            $since->getTimestamp(),
            $range->bucketWidthSeconds(),
            $range->buckets(),
            $metric,
            $samples,
        );
    }

    private function fetchSamples(
        ConnectionId $id,
        SeriesMetric $metric,
        DateTimeImmutable $prevSince,
        DateTimeImmutable $now,
    ): MeasurementSampleCollection {
        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->from(Measurement::class, 'measurement')
            ->where('measurement.connectionId = :connectionId')
            ->andWhere('measurement.completedAt >= :prevSince')
            ->andWhere('measurement.completedAt < :now')
            ->orderBy('measurement.completedAt', 'ASC')
            ->setParameter('connectionId', $id->toString())
            ->setParameter('prevSince', $prevSince)
            ->setParameter('now', $now);

        $this->selectMetricColumns($queryBuilder, $metric);

        /**
         * @var list<array{
         *     completedAt: DateTimeImmutable,
         *     downloadBits?: int|null,
         *     uploadBits?: int|null,
         *     pingSeconds?: float|null,
         *     packetLossRatio?: float|null
         * }> $rows
         */
        $rows = $queryBuilder->getQuery()->getResult();

        $samples = [];

        foreach ($rows as $row) {
            $samples[] = new MeasurementSample(
                completedAtUnix: $row['completedAt']->getTimestamp(),
                downloadBits: $row['downloadBits'] ?? null,
                uploadBits: $row['uploadBits'] ?? null,
                pingSeconds: $row['pingSeconds'] ?? null,
                packetLossRatio: $row['packetLossRatio'] ?? null,
            );
        }

        return MeasurementSampleCollection::fromList($samples);
    }

    private function selectMetricColumns(QueryBuilder $queryBuilder, SeriesMetric $metric): void
    {
        $queryBuilder->select('measurement.completedAt AS completedAt');

        match ($metric) {
            SeriesMetric::Speed => $queryBuilder
                ->addSelect('measurement.downloadBits AS downloadBits')
                ->addSelect('measurement.uploadBits AS uploadBits'),
            SeriesMetric::Ping => $queryBuilder->addSelect('(measurement.ping / 1000.0) AS pingSeconds'),
            SeriesMetric::Loss => $queryBuilder->addSelect('measurement.packetLossRatio AS packetLossRatio'),
        };
    }
}
