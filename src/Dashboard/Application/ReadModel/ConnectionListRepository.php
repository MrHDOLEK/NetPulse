<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

interface ConnectionListRepository
{
    public function all(): ConnectionListItemCollection;
}
