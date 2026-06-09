<?php

declare(strict_types=1);

namespace App\Probe\Domain;

use App\Probe\Domain\Entity\Probe;
use App\Shared\Domain\Collection;

/**
 * @extends Collection<Probe>
 */
final class ProbeCollection extends Collection
{
    public static function of(Probe ...$probes): self
    {
        return new self(array_values($probes));
    }

    /**
     * @param list<Probe> $probes
     */
    public static function fromList(array $probes): self
    {
        return new self($probes);
    }

    /**
     * @return list<Probe>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}
