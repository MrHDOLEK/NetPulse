<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapAggregator;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapGrid;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSample;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapSampleCollection;
use App\Dashboard\Application\ReadModel\HeatmapRepository;
use App\Measurement\Domain\Entity\Measurement;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: HeatmapRepository::class, public: true)]
final readonly class SqlHeatmapRepository implements HeatmapRepository
{
    private const int MIN_SAMPLES = 1;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private HeatmapAggregator $aggregator,
    ) {}

    public function grid(HeatmapQuery $query): HeatmapGrid
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $since = $now->modify("-{$query->window->windowSeconds()} seconds");

        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->from(Measurement::class, 'measurement')
            ->where('measurement.connectionId = :connectionId')
            ->andWhere('measurement.completedAt >= :since')
            ->andWhere('measurement.completedAt < :now')
            ->orderBy('measurement.completedAt', 'ASC')
            ->setParameter('connectionId', $query->connectionId->toString())
            ->setParameter('since', $since)
            ->setParameter('now', $now);

        $this->selectMetric($queryBuilder, $query->metric);

        /**
         * @var list<array{completedAt: DateTimeImmutable, value?: int|float|string|null, healthy?: bool|int|null}> $rows
         */
        $rows = $queryBuilder->getQuery()->getResult();

        $samples = [];

        foreach ($rows as $row) {
            $rawValue = $row['value'] ?? null;
            $rawHealthy = $row['healthy'] ?? null;

            $samples[] = new HeatmapSample(
                completedAtUnix: $row['completedAt']->getTimestamp(),
                value: $rawValue === null ? null : (float) $rawValue,
                healthy: $rawHealthy === null ? null : (bool) $rawHealthy,
            );
        }

        return $this->aggregator->aggregate(
            HeatmapSampleCollection::fromList($samples),
            $query->metric,
            self::MIN_SAMPLES,
        );
    }

    private function selectMetric(QueryBuilder $queryBuilder, HeatmapMetric $metric): void
    {
        $queryBuilder->select('measurement.completedAt AS completedAt');

        match ($metric) {
            HeatmapMetric::Download => $queryBuilder->addSelect('measurement.downloadBits AS value'),
            HeatmapMetric::Ping => $queryBuilder->addSelect('(measurement.ping / 1000.0) AS value'),
            HeatmapMetric::Health => $queryBuilder->addSelect('measurement.healthy AS healthy'),
        };
    }
}
