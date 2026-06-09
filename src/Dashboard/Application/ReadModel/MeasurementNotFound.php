<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Shared\Domain\NotFoundException;

use function sprintf;

final class MeasurementNotFound extends NotFoundException
{
    public static function withId(MeasurementId $id): self
    {
        return new self(sprintf("Measurement %s not found.", $id->toString()));
    }
}
