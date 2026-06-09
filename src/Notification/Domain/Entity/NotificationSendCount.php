<?php

declare(strict_types=1);

namespace App\Notification\Domain\Entity;

class NotificationSendCount
{
    private int $id = 0;
    private string $kind = "";
    private string $channel = "";
    private string $status = "";
    private int $total = 0;

    public function id(): int
    {
        return $this->id;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function total(): int
    {
        return $this->total;
    }
}
