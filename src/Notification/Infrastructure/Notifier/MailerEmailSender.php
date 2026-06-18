<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Notifier;

use App\Notification\Application\Channel\EmailSender;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport as MailerTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(id: EmailSender::class)]
final readonly class MailerEmailSender implements EmailSender
{
    private const string EMAIL_FROM = 'netpulse@localhost';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function send(string $dsn, array $recipients, string $subject, string $body): void
    {
        $mailer = new Mailer(MailerTransport::fromDsn($dsn, null, $this->httpClient));

        foreach ($recipients as $recipient) {
            $email = new Email()
                ->from(self::EMAIL_FROM)
                ->to($recipient)
                ->subject($subject)
                ->text($body);

            $mailer->send($email);
        }
    }
}
