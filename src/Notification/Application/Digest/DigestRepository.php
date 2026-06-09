<?php

declare(strict_types=1);

namespace App\Notification\Application\Digest;

use DateTimeImmutable;

interface DigestRepository
{
    public function since(DateTimeImmutable $since): ConnectionDigestCollection;
}
