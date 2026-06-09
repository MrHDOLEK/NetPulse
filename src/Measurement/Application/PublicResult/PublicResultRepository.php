<?php

declare(strict_types=1);

namespace App\Measurement\Application\PublicResult;

interface PublicResultRepository
{
    /**
     * @throws ResultNotFound when no measurement carries the given share token
     */
    public function get(string $shareToken): PublicResult;
}
