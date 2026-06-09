<?php

declare(strict_types=1);

namespace App\Probe\Domain\Exception;

use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\NotFoundException;

use function sprintf;

final class ProbeNotFound extends NotFoundException
{
    public static function withId(ProbeId $id): self
    {
        return new self(sprintf("Probe %s not found.", $id->toString()));
    }
}
