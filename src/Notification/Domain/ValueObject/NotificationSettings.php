<?php

declare(strict_types=1);

namespace App\Notification\Domain\ValueObject;

final readonly class NotificationSettings
{
    /**
     * @param list<string> $channels active channel names (subset of email/chat/webhook)
     * @param list<string> $emailRecipients To: addresses for the email channel
     */
    public function __construct(
        public bool $enabled,
        public int $consecutiveThreshold,
        public array $channels,
        public array $emailRecipients,
        public string $emailDsn,
        public string $chatDsn,
        public string $webhookUrl,
    ) {}

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }
}
