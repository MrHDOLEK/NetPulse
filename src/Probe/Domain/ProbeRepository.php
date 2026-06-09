<?php

declare(strict_types=1);

namespace App\Probe\Domain;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ValueObject\ProbeId;

interface ProbeRepository
{
    public function save(Probe $probe): void;

    public function delete(Probe $probe): void;

    /**
     * @throws ProbeNotFound
     */
    public function get(ProbeId $id): Probe;

    public function find(ProbeId $id): ?Probe;

    public function all(): ProbeCollection;
}
