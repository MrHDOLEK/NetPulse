<?php

declare(strict_types=1);

namespace App\Measurement\Application\Share;

use App\Measurement\Domain\Exception\MeasurementNotFound;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;

final readonly class ShareMeasurement
{
    public function __construct(
        private MeasurementRepository $measurements,
    ) {}

    /**
     * @throws MeasurementNotFound
     */
    public function __invoke(MeasurementId $id): string
    {
        $measurement = $this->measurements->get($id);
        $token = $measurement->share();
        $this->measurements->save($measurement);

        return $token;
    }
}
