<?php

declare(strict_types=1);

namespace App\Notification\Application\Channel;

interface ChatSender
{
    public function send(string $dsn, string $text): void;
}
