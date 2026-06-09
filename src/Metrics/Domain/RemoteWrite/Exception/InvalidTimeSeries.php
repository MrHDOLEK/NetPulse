<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Exception;

use App\Shared\Domain\DomainException;

final class InvalidTimeSeries extends DomainException
{
    public static function missingNameLabel(): self
    {
        return new self("TimeSeries must contain a __name__ label.");
    }
}
