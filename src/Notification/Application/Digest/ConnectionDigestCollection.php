<?php

declare(strict_types=1);

namespace App\Notification\Application\Digest;

use App\Shared\Domain\Collection;

/**
 * @extends Collection<ConnectionDigest>
 */
final class ConnectionDigestCollection extends Collection
{
    public static function of(ConnectionDigest ...$digests): self
    {
        return new self(array_values($digests));
    }

    /**
     * @param list<ConnectionDigest> $digests
     */
    public static function fromList(array $digests): self
    {
        return new self($digests);
    }

    /**
     * @return list<ConnectionDigest>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
