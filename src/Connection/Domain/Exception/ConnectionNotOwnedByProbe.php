<?php

declare(strict_types=1);

namespace App\Connection\Domain\Exception;

use App\Shared\Domain\DomainException;

final class ConnectionNotOwnedByProbe extends DomainException
{
}
