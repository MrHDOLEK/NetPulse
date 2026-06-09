<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\DashboardCursor;
use App\Dashboard\Application\ReadModel\DashboardCursorRepository;
use App\Measurement\Domain\Entity\Measurement;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: DashboardCursorRepository::class, public: true)]
final readonly class SqlDashboardCursorRepository implements DashboardCursorRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function current(): DashboardCursor
    {
        /** @var array{latest: string|null, total: int|string} $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select(
                "MAX(measurement.completedAt) AS latest",
                "COUNT(measurement.id) AS total",
            )
            ->from(Measurement::class, "measurement")
            ->getQuery()
            ->getSingleResult();

        $latest = $row["latest"] === null
            ? null
            : new DateTimeImmutable($row["latest"], new DateTimeZone("UTC"))->getTimestamp();

        return new DashboardCursor($latest, (int)$row["total"]);
    }
}
