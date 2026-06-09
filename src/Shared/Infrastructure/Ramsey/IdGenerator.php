<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Ramsey;

use App\Shared\Application\Service\IdGeneratorInterface;
use App\Shared\Domain\Id;
use Ramsey\Uuid\Uuid;

final class IdGenerator implements IdGeneratorInterface
{
    public function generate(): Id
    {
        return new Id(Uuid::uuid7()->toString());
    }
}
