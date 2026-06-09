<?php

declare(strict_types=1);

namespace App\Metrics\Domain\Entity;

class RemoteWriteFailureCount
{
    public const int SINGLETON_ID = 1;

    private int $id = self::SINGLETON_ID;
    private int $total = 0;

    public function id(): int
    {
        return $this->id;
    }

    public function total(): int
    {
        return $this->total;
    }
}
