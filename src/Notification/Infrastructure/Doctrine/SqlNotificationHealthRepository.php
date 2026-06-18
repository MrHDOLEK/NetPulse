<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Notification\Application\NotificationHealthRepository;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: NotificationHealthRepository::class, public: true)]
final readonly class SqlNotificationHealthRepository implements NotificationHealthRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function forConnection(ConnectionId $connectionId, int $limit): HealthHistory
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
            ->setParameter('connectionId', $connectionId, 'connection_id')
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
