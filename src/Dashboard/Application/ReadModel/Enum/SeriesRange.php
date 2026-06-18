<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

use InvalidArgumentException;

enum SeriesRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';

    public static function fromParam(string $param): self
    {
        $range = self::tryFrom($param);

        if ($range === null) {
            throw new InvalidArgumentException("Unknown series range: {$param}");
        }

        return $range;
    }

    public function windowSeconds(): int
    {
        return match ($this) {
            self::Day => 86_400,
            self::Week => 604_800,
            self::Month => 2_592_000,
            self::Quarter => 7_776_000,
        };
    }

    public function buckets(): int
    {
        return match ($this) {
            self::Day => 48,
            self::Week => 84,
            self::Month => 90,
            self::Quarter => 120,
        };
    }

    public function bucketWidthSeconds(): int
    {
        return intdiv($this->windowSeconds(), $this->buckets());
    }
}
