<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Notifier;

use App\Notification\Application\Channel\ChatSender;
use App\Notification\Application\Channel\EmailSender;
use App\Notification\Application\NotificationSettingsProvider;
use App\Notification\Application\NotificationTester;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\NotificationDispatcher;
use App\Notification\Domain\NotificationSendCounter;
use App\Notification\Domain\ValueObject\Notification;
use App\Notification\Domain\ValueObject\NotificationSettings;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsAlias(id: NotificationDispatcher::class)]
#[AsAlias(id: NotificationTester::class)]
final readonly class SymfonyNotifierDispatcher implements NotificationDispatcher, NotificationTester
{
    private const string CHANNEL_EMAIL = "email";
    private const string CHANNEL_CHAT = "chat";
    private const string CHANNEL_WEBHOOK = "webhook";
    private const string STATUS_SENT = "sent";
    private const string STATUS_FAILED = "failed";
    private const string STATUS_SKIPPED = "skipped";

    public function __construct(
        private HttpClientInterface $httpClient,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private NotificationSendCounter $sendCounter,
        private NotificationSettingsProvider $settingsProvider,
        private EmailSender $emailSender,
        private ChatSender $chatSender,
    ) {}

    public function send(Notification $notification): void
    {
        $settings = $this->settingsProvider->current();

        foreach ($settings->channels as $channel) {
            $status = $this->attempt($channel, $notification, $settings);
            $this->sendCounter->increment($notification->kind, $channel, $status);
        }
    }

    public function test(): array
    {
        $settings = $this->settingsProvider->current();
        $notification = $this->sampleNotification();
        $results = [];

        foreach ($settings->channels as $channel) {
            $results[$channel] = $this->humanize($this->attempt($channel, $notification, $settings));
        }

        return $results;
    }

    /**
     * @return self::STATUS_*
     */
    private function attempt(string $channel, Notification $notification, NotificationSettings $settings): string
    {
        try {
            return match ($channel) {
                self::CHANNEL_EMAIL => $this->sendEmail($notification, $settings),
                self::CHANNEL_CHAT => $this->sendChat($notification, $settings),
                self::CHANNEL_WEBHOOK => $this->sendWebhook($notification, $settings),
                default => $this->unknownChannel($channel),
            };
        } catch (Throwable $exception) {
            $this->logger->error("notification channel failed", [
                "channel" => $channel,
                "error" => $this->redactSecrets($exception->getMessage(), $settings),
            ]);

            return self::STATUS_FAILED;
        }
    }

    /**
     * @return self::STATUS_SENT|self::STATUS_SKIPPED
     */
    private function sendEmail(Notification $notification, NotificationSettings $settings): string
    {
        if ($settings->emailRecipients === [] || trim($settings->emailDsn) === "") {
            return $this->skip(self::CHANNEL_EMAIL);
        }

        $this->emailSender->send(
            $settings->emailDsn,
            $settings->emailRecipients,
            $notification->subject,
            $notification->body,
        );

        return self::STATUS_SENT;
    }

    /**
     * @return self::STATUS_SENT|self::STATUS_SKIPPED
     */
    private function sendChat(Notification $notification, NotificationSettings $settings): string
    {
        if (trim($settings->chatDsn) === "") {
            return $this->skip(self::CHANNEL_CHAT);
        }

        $this->chatSender->send($settings->chatDsn, $notification->subject . "\n\n" . $notification->body);

        return self::STATUS_SENT;
    }

    /**
     * @return self::STATUS_SENT|self::STATUS_SKIPPED
     */
    private function sendWebhook(Notification $notification, NotificationSettings $settings): string
    {
        if (trim($settings->webhookUrl) === "") {
            return $this->skip(self::CHANNEL_WEBHOOK);
        }

        $payload = [
            "kind" => $notification->kind->value,
            "severity" => $notification->severity->value,
            "subject" => $notification->subject,
            "body" => $notification->body,
            "context" => $notification->context,
            "timestamp" => $this->clock->now()->format(DATE_ATOM),
        ];

        $response = $this->httpClient->request("POST", $settings->webhookUrl, [
            "json" => $payload,
        ]);

        $httpStatus = $response->getStatusCode();

        if ($httpStatus < Response::HTTP_OK || $httpStatus >= Response::HTTP_MULTIPLE_CHOICES) {
            throw new WebhookDeliveryFailed($httpStatus);
        }

        return self::STATUS_SENT;
    }

    private function sampleNotification(): Notification
    {
        return new Notification(
            NotificationKind::Alert,
            NotificationSeverity::Info,
            "NetPulse test notification",
            "This is a test alert from NetPulse — if you can read it, this channel is configured correctly.",
            ["test" => true],
        );
    }

    private function redactSecrets(string $message, NotificationSettings $settings): string
    {
        foreach ([$settings->webhookUrl, $settings->chatDsn, $settings->emailDsn] as $secret) {
            if (trim($secret) !== "") {
                $message = str_replace($secret, "[redacted]", $message);
            }
        }

        return $message;
    }

    /**
     * @param self::STATUS_* $status
     */
    private function humanize(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT => "sent",
            self::STATUS_SKIPPED => "skipped (not configured)",
            default => "failed (check the logs)",
        };
    }

    /**
     * @return self::STATUS_SKIPPED
     */
    private function skip(string $channel): string
    {
        $this->logger->warning("notification channel skipped", [
            "channel" => $channel,
            "reason" => "not_configured",
        ]);

        return self::STATUS_SKIPPED;
    }

    /**
     * @return self::STATUS_SKIPPED
     */
    private function unknownChannel(string $channel): string
    {
        $this->logger->warning("notification channel skipped", [
            "channel" => $channel,
            "reason" => "unknown_channel",
        ]);

        return self::STATUS_SKIPPED;
    }
}
