<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Notifier;

use App\Notification\Application\Channel\ChatSender;
use App\Notification\Application\Channel\EmailSender;
use App\Notification\Application\NotificationSettingsProvider;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\NotificationSendCounter;
use App\Notification\Domain\ValueObject\Notification;
use App\Notification\Domain\ValueObject\NotificationSettings;
use App\Notification\Infrastructure\Notifier\SymfonyNotifierDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SymfonyNotifierDispatcherTest extends TestCase
{
    private const string WEBHOOK_URL = 'https://hooks.example.test/netpulse';
    private const string NOW = '2026-06-06T12:00:00+00:00';

    public function testWebhookChannelPostsJsonPayload(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (
            &$requests,
        ): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('', ['http_code' => Response::HTTP_OK]);
        });
        $counter = new RecordingSendCounter();

        $this->dispatcher(
            channels: 'webhook',
            webhookUrl: self::WEBHOOK_URL,
            httpClient: $httpClient,
            sendCounter: $counter,
        )->send($this->alert());

        self::assertSame([['alert', 'webhook', 'sent']], $counter->increments);

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame(self::WEBHOOK_URL, $requests[0]['url']);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($this->requestBody($requests[0]['options']), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('alert', $payload['kind']);
        self::assertSame('critical', $payload['severity']);
        self::assertSame('Subject line', $payload['subject']);
        self::assertSame('Body text', $payload['body']);
        self::assertSame(self::NOW, $payload['timestamp']);
        self::assertSame('wan1', $payload['context']['connection']);
    }

    public function testEmailChannelSendsToEveryRecipientWithTheSavedDsn(): void
    {
        $emailSender = new RecordingEmailSender();
        $counter = new RecordingSendCounter();

        $this->dispatcher(
            channels: 'email',
            emailTo: 'ops@example.test, oncall@example.test',
            emailDsn: 'smtp://mail.example.test:587',
            emailSender: $emailSender,
            sendCounter: $counter,
        )->send($this->alert());

        self::assertSame([['alert', 'email', 'sent']], $counter->increments);

        self::assertCount(1, $emailSender->sent);
        $sent = $emailSender->sent[0];
        self::assertSame('smtp://mail.example.test:587', $sent['dsn']);
        self::assertSame(['ops@example.test', 'oncall@example.test'], $sent['recipients']);
        self::assertSame('Subject line', $sent['subject']);
        self::assertSame('Body text', $sent['body']);
    }

    public function testChatChannelSendsOneChatMessage(): void
    {
        $chatSender = new RecordingChatSender();

        $this->dispatcher(channels: 'chat', chatDsn: 'slack://token@default', chatSender: $chatSender)->send(
            $this->alert(),
        );

        self::assertCount(1, $chatSender->sent);
        self::assertSame('slack://token@default', $chatSender->sent[0]['dsn']);
        self::assertStringContainsString('Subject line', $chatSender->sent[0]['text']);
        self::assertStringContainsString('Body text', $chatSender->sent[0]['text']);
    }

    public function testNoChannelsConfiguredSendsNothing(): void
    {
        $emailSender = new RecordingEmailSender();
        $chatSender = new RecordingChatSender();
        $counter = new RecordingSendCounter();
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (
            &$requests,
        ): MockResponse {
            $requests[] = $url;

            return new MockResponse('', ['http_code' => Response::HTTP_OK]);
        });

        $this->dispatcher(
            channels: '',
            emailSender: $emailSender,
            chatSender: $chatSender,
            httpClient: $httpClient,
            sendCounter: $counter,
        )->send($this->alert());

        self::assertSame([], $emailSender->sent);
        self::assertSame([], $chatSender->sent);
        self::assertSame([], $requests);

        self::assertSame([], $counter->increments);
    }

    public function testChannelEnabledButUnconfiguredIsSkippedWithWarning(): void
    {
        $logger = new RecordingLogger();
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (
            &$requests,
        ): MockResponse {
            $requests[] = $url;

            return new MockResponse('', ['http_code' => Response::HTTP_OK]);
        });

        $counter = new RecordingSendCounter();

        $this->dispatcher(
            channels: 'webhook',
            webhookUrl: '',
            httpClient: $httpClient,
            logger: $logger,
            sendCounter: $counter,
        )->send($this->alert());

        self::assertSame([], $requests);
        self::assertTrue($logger->has('warning', 'notification channel skipped'));
        self::assertSame('webhook', $logger->lastContext('warning')['channel'] ?? null);
        self::assertSame('not_configured', $logger->lastContext('warning')['reason'] ?? null);

        self::assertSame([['alert', 'webhook', 'skipped']], $counter->increments);
    }

    public function testOneFailingChannelDoesNotDropTheOthers(): void
    {
        $logger = new RecordingLogger();
        $emailSender = new RecordingEmailSender();
        $counter = new RecordingSendCounter();

        $httpClient = new MockHttpClient(
            static fn(): MockResponse => new MockResponse('boom', [
                'http_code' => Response::HTTP_INTERNAL_SERVER_ERROR,
            ]),
        );

        $this->dispatcher(
            channels: 'webhook,email',
            emailTo: 'ops@example.test',
            emailDsn: 'smtp://mail.example.test:587',
            webhookUrl: self::WEBHOOK_URL,
            emailSender: $emailSender,
            httpClient: $httpClient,
            logger: $logger,
            sendCounter: $counter,
        )->send($this->alert());

        self::assertCount(1, $emailSender->sent);
        self::assertTrue($logger->has('error', 'notification channel failed'));
        self::assertSame('webhook', $logger->lastContext('error')['channel'] ?? null);

        self::assertStringNotContainsString(self::WEBHOOK_URL, (string) ($logger->lastContext('error')['error'] ?? ''));

        self::assertSame(
            [
                ['alert', 'webhook', 'failed'],
                ['alert', 'email',   'sent'],
            ],
            $counter->increments,
        );
    }

    public function testTestReturnsPerChannelResultsWithoutTouchingTheCounter(): void
    {
        $emailSender = new RecordingEmailSender();
        $counter = new RecordingSendCounter();
        $httpClient = new MockHttpClient(
            static fn(): MockResponse => new MockResponse('', ['http_code' => Response::HTTP_OK]),
        );

        $results = $this->dispatcher(
            channels: 'email,webhook',
            emailTo: 'ops@example.test',
            emailDsn: 'smtp://mail.example.test:587',
            webhookUrl: self::WEBHOOK_URL,
            emailSender: $emailSender,
            httpClient: $httpClient,
            sendCounter: $counter,
        )->test();

        self::assertSame(['email' => 'sent', 'webhook' => 'sent'], $results);

        self::assertCount(1, $emailSender->sent);

        self::assertSame([], $counter->increments);
    }

    private function alert(): Notification
    {
        return new Notification(NotificationKind::Alert, NotificationSeverity::Critical, 'Subject line', 'Body text', [
            'probe' => 'home',
            'connection' => 'wan1',
            'reason' => '3 consecutive unhealthy measurements',
        ]);
    }

    private function dispatcher(
        string $channels = '',
        string $emailTo = '',
        string $emailDsn = 'smtp://localhost:25',
        string $chatDsn = '',
        string $webhookUrl = '',
        ?RecordingEmailSender $emailSender = null,
        ?RecordingChatSender $chatSender = null,
        ?HttpClientInterface $httpClient = null,
        ?RecordingLogger $logger = null,
        ?RecordingSendCounter $sendCounter = null,
    ): SymfonyNotifierDispatcher {
        $settings = new NotificationSettings(
            enabled: true,
            consecutiveThreshold: 3,
            channels: $this->csv($channels),
            emailRecipients: $this->csv($emailTo),
            emailDsn: $emailDsn,
            chatDsn: $chatDsn,
            webhookUrl: $webhookUrl,
        );

        return new SymfonyNotifierDispatcher(
            $httpClient ?? new MockHttpClient(),
            new MockClock(self::NOW),
            $logger ?? new RecordingLogger(),
            $sendCounter ?? new RecordingSendCounter(),
            new FakeNotificationSettingsProvider($settings),
            $emailSender ?? new RecordingEmailSender(),
            $chatSender ?? new RecordingChatSender(),
        );
    }

    /**
     * @return list<string>
     */
    private function csv(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(string $item): string => trim($item), explode(',', $value)),
            static fn(string $item): bool => $item !== '',
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestBody(array $options): string
    {
        $body = $options['body'] ?? '';

        if (is_string($body)) {
            return $body;
        }

        if (is_iterable($body)) {
            $buffer = '';

            foreach ($body as $chunk) {
                $buffer .= is_string($chunk) ? $chunk : '';
            }

            return $buffer;
        }

        return '';
    }
}

final readonly class FakeNotificationSettingsProvider implements NotificationSettingsProvider
{
    public function __construct(
        private NotificationSettings $settings,
    ) {}

    public function current(): NotificationSettings
    {
        return $this->settings;
    }
}

final class RecordingSendCounter implements NotificationSendCounter
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $increments = [];

    public function increment(NotificationKind $kind, string $channel, string $status): void
    {
        $this->increments[] = [$kind->value, $channel, $status];
    }
}

final class RecordingEmailSender implements EmailSender
{
    /** @var list<array{dsn: string, recipients: list<string>, subject: string, body: string}> */
    public array $sent = [];

    public function send(string $dsn, array $recipients, string $subject, string $body): void
    {
        $this->sent[] = ['dsn' => $dsn, 'recipients' => $recipients, 'subject' => $subject, 'body' => $body];
    }
}

final class RecordingChatSender implements ChatSender
{
    /** @var list<array{dsn: string, text: string}> */
    public array $sent = [];

    public function send(string $dsn, string $text): void
    {
        $this->sent[] = ['dsn' => $dsn, 'text' => $text];
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function has(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastContext(string $level): array
    {
        $context = [];

        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                $context = $record['context'];
            }
        }

        return $context;
    }
}
