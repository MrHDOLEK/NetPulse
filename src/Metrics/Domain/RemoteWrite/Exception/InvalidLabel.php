<?php

declare(strict_types=1);

namespace App\Metrics\Domain\RemoteWrite\Exception;

use App\Shared\Domain\DomainException;

final class InvalidLabel extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Remote write label name must not be empty.');
    }
}
