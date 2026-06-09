<?php

declare(strict_types=1);

namespace App\Notification\Application;

interface NotificationTester
{
    /**
     * @return array<string, string> channel name => human-readable result ("sent", "skipped …", "failed …")
     */
    public function test(): array;
}
