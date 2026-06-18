<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Exception;

use App\Shared\Domain\NotFoundException;

final class MeasurementNotFound extends NotFoundException {}
