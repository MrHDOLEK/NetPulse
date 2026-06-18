<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine;

use App\Connection\Domain\ConnectionCollection;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Exception\ConnectionNotFound;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ConnectionRepository::class)]
final class DoctrineConnectionRepository implements ConnectionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Connection $connection): void
    {
        $this->entityManager->persist($connection);
        $this->entityManager->flush();
    }

    public function delete(Connection $connection): void
    {
        $this->entityManager->remove($connection);
        $this->entityManager->flush();
    }

    public function get(ConnectionId $connectionId): Connection
    {
        $connection = $this->find($connectionId);

        if ($connection === null) {
            throw ConnectionNotFound::withId($connectionId);
        }

        return $connection;
    }

    public function find(ConnectionId $connectionId): ?Connection
    {
        $connection = $this->entityManager
            ->createQueryBuilder()
            ->select('connection')
            ->from(Connection::class, 'connection')
            ->where('connection.id = :id')
            ->setParameter('id', $connectionId, 'connection_id')
            ->getQuery()
            ->getOneOrNullResult();

        if ($connection === null) {
            return null;
        }

        if (!$connection instanceof Connection) {
            throw new LogicException('Expected query to return a Connection instance.');
        }

        return $connection;
    }

    public function byProbe(ProbeId $probeId): ConnectionCollection
    {
        /** @var list<Connection> $connections */
        $connections = $this->entityManager
            ->createQueryBuilder()
            ->select('connection')
            ->from(Connection::class, 'connection')
            ->where('connection.probeId = :probeId')
            ->setParameter('probeId', $probeId, 'probe_id')
            ->getQuery()
            ->getResult();

        return ConnectionCollection::fromList($connections);
    }

    public function allEnabled(): ConnectionCollection
    {
        /** @var list<Connection> $connections */
        $connections = $this->entityManager
            ->createQueryBuilder()
            ->select('connection')
            ->from(Connection::class, 'connection')
            ->where('connection.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();

        return ConnectionCollection::fromList($connections);
    }

    public function all(): ConnectionCollection
    {
        /** @var list<Connection> $connections */
        $connections = $this->entityManager
            ->createQueryBuilder()
            ->select('connection')
            ->from(Connection::class, 'connection')
            ->orderBy('connection.name', 'ASC')
            ->getQuery()
            ->getResult();

        return ConnectionCollection::fromList($connections);
    }
}
