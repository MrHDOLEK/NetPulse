<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Format;

final class UnitFormatter
{
    private const string NULL_PLACEHOLDER = '—';
    private const int BITS_PER_MBPS = 1_000_000;
    private const int MBPS_PER_GBPS = 1_000;

    public static function bitsPerSecond(?int $bitsPerSecond): string
    {
        if ($bitsPerSecond === null) {
            return self::NULL_PLACEHOLDER;
        }

        $mbps = $bitsPerSecond / self::BITS_PER_MBPS;

        if ($mbps >= self::MBPS_PER_GBPS) {
            return number_format($mbps / self::MBPS_PER_GBPS, 1) . ' Gbps';
        }

        return self::trimmedOneDecimal($mbps) . ' Mbps';
    }

    public static function seconds(?float $seconds): string
    {
        if ($seconds === null) {
            return self::NULL_PLACEHOLDER;
        }

        return self::trimmedOneDecimal($seconds * 1000) . ' ms';
    }

    public static function ratio(?float $ratio): string
    {
        if ($ratio === null) {
            return self::NULL_PLACEHOLDER;
        }

        return self::trimmedOneDecimal($ratio * 100) . ' %';
    }

    private static function trimmedOneDecimal(float $value): string
    {
        $rounded = round($value, 1);

        if ($rounded === floor($rounded)) {
            return number_format($rounded, 0, '.', '');
        }

        return number_format($rounded, 1, '.', '');
    }
}
