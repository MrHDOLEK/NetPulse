<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

use InvalidArgumentException;

enum SeriesMetric: string
{
    case Speed = 'speed';
    case Ping = 'ping';
    case Loss = 'loss';

    public static function fromParam(string $param): self
    {
        $metric = self::tryFrom($param);

        if ($metric === null) {
            throw new InvalidArgumentException("Unknown series metric: {$param}");
        }

        return $metric;
    }
}
