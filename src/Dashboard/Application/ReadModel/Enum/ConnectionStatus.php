<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

enum ConnectionStatus: string
{
    case Healthy = "healthy";
    case Degraded = "degraded";
    case Down = "down";
}
