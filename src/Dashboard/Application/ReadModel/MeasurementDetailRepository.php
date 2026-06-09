<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Measurement\Domain\ValueObject\MeasurementId;

interface MeasurementDetailRepository
{
    /**
     * @throws MeasurementNotFound when no measurement matches the id
     */
    public function get(MeasurementId $id): MeasurementDetail;
}
