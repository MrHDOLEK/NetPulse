<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

interface ProbeLivenessRepository
{
    /**
     * @return list<ProbeLiveness>
     */
    public function all(): array;
}
