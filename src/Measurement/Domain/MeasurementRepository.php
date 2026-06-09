<?php

declare(strict_types=1);

namespace App\Measurement\Domain;

use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\ValueObject\MeasurementId;

interface MeasurementRepository
{
    public function save(Measurement $measurement): void;

    /**
     * @throws MeasurementNotFound
     */
    public function get(MeasurementId $id): Measurement;

    public function find(MeasurementId $id): ?Measurement;
}
