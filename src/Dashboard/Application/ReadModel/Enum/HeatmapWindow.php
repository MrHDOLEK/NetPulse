<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

use InvalidArgumentException;

enum HeatmapWindow: string
{
    case Month = '30d';
    case Quarter = '90d';

    public static function fromParam(string $param): self
    {
        return self::tryFrom($param) ?? throw new InvalidArgumentException("Unknown heatmap window: {$param}");
    }

    public function windowSeconds(): int
    {
        return match ($this) {
            self::Month => 2_592_000,
            self::Quarter => 7_776_000,
        };
    }
}
