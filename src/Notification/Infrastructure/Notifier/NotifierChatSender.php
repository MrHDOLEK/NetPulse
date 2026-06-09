<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Notifier;

use App\Notification\Application\Channel\ChatSender;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport as NotifierTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(id: ChatSender::class)]
final readonly class NotifierChatSender implements ChatSender
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function send(string $dsn, string $text): void
    {
        NotifierTransport::fromDsn($dsn, null, $this->httpClient)->send(new ChatMessage($text));
    }
}
