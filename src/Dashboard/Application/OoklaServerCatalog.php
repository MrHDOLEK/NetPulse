<?php

declare(strict_types=1);

namespace App\Dashboard\Application;

interface OoklaServerCatalog
{
    /**
     * @return list<OoklaServer>
     */
    public function servers(): array;
}
