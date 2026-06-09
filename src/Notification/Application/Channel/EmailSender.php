<?php

declare(strict_types=1);

namespace App\Notification\Application\Channel;

interface EmailSender
{
    /**
     * @param list<string> $recipients
     */
    public function send(string $dsn, array $recipients, string $subject, string $body): void;
}
