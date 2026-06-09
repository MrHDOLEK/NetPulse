<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel;

final readonly class ServerListItem
{
    public function __construct(
        public string $serverId,
        public string $name,
        public string $location,
    ) {}
}
