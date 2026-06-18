<?php

declare(strict_types=1);

namespace App\Connection\Domain\Exception;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Shared\Domain\NotFoundException;

final class ConnectionNotFound extends NotFoundException
{
    public static function withId(ConnectionId $connectionId): self
    {
        return new self('Connection ' . $connectionId->toString() . ' not found.');
    }
}
