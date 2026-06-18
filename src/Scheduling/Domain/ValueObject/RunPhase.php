<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum RunPhase: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
}
