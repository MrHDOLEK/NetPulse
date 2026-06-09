<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Doctrine;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ProbeCollection;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProbeRepository::class)]
final class DoctrineProbeRepository implements ProbeRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Probe $probe): void
    {
        $this->entityManager->persist($probe);
        $this->entityManager->flush();
    }

    public function delete(Probe $probe): void
    {
        $this->entityManager->remove($probe);
        $this->entityManager->flush();
    }

    public function get(ProbeId $id): Probe
    {
        $probe = $this->find($id);

        if ($probe === null) {
            throw ProbeNotFound::withId($id);
        }

        return $probe;
    }

    public function find(ProbeId $id): ?Probe
    {
        $probe = $this->entityManager
            ->createQueryBuilder()
            ->select("probe")
            ->from(Probe::class, "probe")
            ->where("probe.id = :id")
            ->setParameter("id", $id, "probe_id")
            ->getQuery()
            ->getOneOrNullResult();

        if ($probe === null) {
            return null;
        }

        if (!$probe instanceof Probe) {
            throw new LogicException("Expected query to return a Probe instance.");
        }

        return $probe;
    }

    public function all(): ProbeCollection
    {
        /** @var list<Probe> $probes */
        $probes = $this->entityManager
            ->createQueryBuilder()
            ->select("probe")
            ->from(Probe::class, "probe")
            ->orderBy("probe.createdAt", "DESC")
            ->getQuery()
            ->getResult();

        return ProbeCollection::fromList($probes);
    }
}
