<?php

declare(strict_types=1);

namespace App\Measurement\Infrastructure\Doctrine;

use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MeasurementRepository::class)]
final class DoctrineMeasurementRepository implements MeasurementRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Measurement $measurement): void
    {
        $this->entityManager->persist($measurement);
        $this->entityManager->flush();
    }

    public function get(MeasurementId $id): Measurement
    {
        $measurement = $this->find($id);

        if ($measurement === null) {
            throw new MeasurementNotFound(sprintf('Measurement %s not found.', $id->toString()));
        }

        return $measurement;
    }

    public function find(MeasurementId $id): ?Measurement
    {
        return $this->entityManager->find(Measurement::class, $id);
    }
}
