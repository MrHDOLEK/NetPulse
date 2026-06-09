<?php

declare(strict_types=1);

namespace App\Tests\Integration\Metrics\Infrastructure\Doctrine;

use App\Metrics\Domain\Entity\RemoteWriteFailureCount;
use App\Metrics\Infrastructure\Doctrine\DoctrineRemoteWriteFailureCounter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRemoteWriteFailureCounterTest extends KernelTestCase
{
    public function testIncrementAtomicallyAccumulatesTotal(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $connection->executeStatement("DELETE FROM remote_write_failures");

        $counter = new DoctrineRemoteWriteFailureCounter($entityManager);

        self::assertSame(0, $counter->total());

        $counter->increment();
        $counter->increment();
        $counter->increment();

        self::assertSame(3, $counter->total());

        $row = $connection->fetchAssociative("SELECT id, total FROM remote_write_failures WHERE id = 1");
        self::assertNotFalse($row);
        self::assertSame(RemoteWriteFailureCount::SINGLETON_ID, (int)$row["id"]);
        self::assertSame(3, (int)$row["total"]);
    }
}
