<?php

declare(strict_types=1);

namespace App\Metrics\Domain;

interface RemoteWriteFailureCounter
{
    public function increment(): void;

    public function total(): int;
}
