<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Doctrine;

use App\Metrics\Domain\Entity\RemoteWriteFailureCount;
use App\Metrics\Domain\RemoteWriteFailureCounter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: RemoteWriteFailureCounter::class)]
final readonly class DoctrineRemoteWriteFailureCounter implements RemoteWriteFailureCounter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function increment(): void
    {
        $this->ensureRowExists();

        $this->entityManager
            ->createQuery(
                "UPDATE " . RemoteWriteFailureCount::class . " c "
                . "SET c.total = c.total + 1 "
                . "WHERE c.id = :id",
            )
            ->setParameter("id", RemoteWriteFailureCount::SINGLETON_ID)
            ->execute();
    }

    public function total(): int
    {
        $total = $this->entityManager
            ->createQuery(
                "SELECT c.total FROM " . RemoteWriteFailureCount::class . " c WHERE c.id = :id",
            )
            ->setParameter("id", RemoteWriteFailureCount::SINGLETON_ID)
            ->getOneOrNullResult();

        if (is_array($total) && isset($total["total"]) && is_int($total["total"])) {
            return $total["total"];
        }

        return 0;
    }

    private function ensureRowExists(): void
    {
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement(
            "INSERT OR IGNORE INTO remote_write_failures (id, total) VALUES (:id, 0)",
            ["id" => RemoteWriteFailureCount::SINGLETON_ID],
        );
    }
}
