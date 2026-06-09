<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

interface ServerListRepository
{
    public function all(): ServerListItemCollection;
}
