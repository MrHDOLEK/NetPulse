<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Collection;

use App\Measurement\Domain\Enum\ThresholdBreach;
use App\Shared\Domain\Collection;

use function array_values;

/**
 * @extends Collection<ThresholdBreach>
 */
final class ThresholdBreachCollection extends Collection
{
    public static function of(ThresholdBreach ...$breaches): self
    {
        return new self(array_values($breaches));
    }
}
