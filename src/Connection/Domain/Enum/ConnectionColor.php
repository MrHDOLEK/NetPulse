<?php

declare(strict_types=1);

namespace App\Connection\Domain\Enum;

enum ConnectionColor: string
{
    case Primary = "primary";
    case Violet = "violet";
    case Amber = "amber";

    public static function default(): self
    {
        return self::Primary;
    }
}
