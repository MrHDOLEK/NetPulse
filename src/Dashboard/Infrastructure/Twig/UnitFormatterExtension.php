<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Twig;

use App\Dashboard\Application\Format\UnitFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class UnitFormatterExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('mbps', UnitFormatter::bitsPerSecond(...)),
            new TwigFilter('ms', UnitFormatter::seconds(...)),
            new TwigFilter('pct', UnitFormatter::ratio(...)),
        ];
    }
}
