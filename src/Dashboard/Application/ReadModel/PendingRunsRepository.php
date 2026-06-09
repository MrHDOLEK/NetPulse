<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

interface PendingRunsRepository
{
    /**
     * @return list<PendingRun>
     */
    public function pending(): array;
}
