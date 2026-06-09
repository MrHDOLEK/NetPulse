<?php

declare(strict_types=1);

namespace App\Probe\Domain\Exception;

use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\DomainException;

use function sprintf;

final class ProbeHasConnections extends DomainException
{
    public static function withId(ProbeId $id): self
    {
        return new self(sprintf("Probe %s still has connections and cannot be deleted.", $id->toString()));
    }
}
