<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Enum;

use InvalidArgumentException;

enum HeatmapMetric: string
{
    case Download = "download";
    case Health = "health";
    case Ping = "ping";

    public static function fromParam(string $param): self
    {
        return self::tryFrom($param) ?? throw new InvalidArgumentException("Unknown heatmap metric: {$param}");
    }

    public function unit(): string
    {
        return match ($this) {
            self::Download => "Mbps",
            self::Health => "%",
            self::Ping => "ms",
        };
    }

    public function higherIsBetter(): bool
    {
        return $this !== self::Ping;
    }

    public function label(): string
    {
        return match ($this) {
            self::Download => "Download",
            self::Health => "Health",
            self::Ping => "Ping",
        };
    }
}
