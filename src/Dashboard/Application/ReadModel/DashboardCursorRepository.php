<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

interface DashboardCursorRepository
{
    public function current(): DashboardCursor;
}
