<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\RecentHealthRepository;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: RecentHealthRepository::class, public: true)]
final readonly class SqlRecentHealthRepository implements RecentHealthRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function recent(ConnectionId $id, int $limit = 60): HealthHistory
    {
        /**
         * @var list<array{
         *     completedAt: DateTimeImmutable,
         *     status: MeasurementStatus,
         *     healthy: bool|null
         * }> $rows
         */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'measurement.completedAt AS completedAt',
                'measurement.status AS status',
                'measurement.healthy AS healthy',
            )
            ->from(Measurement::class, 'measurement')
            ->where('measurement.connectionId = :connectionId')
            ->setParameter('connectionId', $id, 'connection_id')
            ->orderBy('measurement.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $samples = [];

        foreach ($rows as $row) {
            $samples[] = $this->toSample($row['completedAt'], $row['status'], $row['healthy']);
        }

        return HealthHistory::fromList($samples);
    }

    private function toSample(DateTimeImmutable $completedAt, MeasurementStatus $status, ?bool $healthy): HealthSample
    {
        if ($status === MeasurementStatus::Failed) {
            return HealthSample::failed($completedAt);
        }

        return HealthSample::completed($completedAt, $healthy);
    }
}
