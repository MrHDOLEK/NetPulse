<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Notifier;

use App\Notification\Application\Digest\ConnectionDigest;
use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Infrastructure\Notifier\TwigNotificationRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

final class TwigNotificationRendererTest extends TestCase
{
    private const string TEMPLATES_DIR = __DIR__ . '/../../../../../templates/notification';

    /**
     * @return iterable<string, array{
     *     0: NotificationKind,
     *     1: NotificationSeverity,
     *     2: array<string, scalar>,
     *     3: string,
     *     4: string
     * }>
     */
    public static function renderCases(): iterable
    {
        yield 'alert' => [
            NotificationKind::Alert,
            NotificationSeverity::Critical,
            self::context(),
            '[NetPulse] home/wan1 is unhealthy',
            'Connection "wan1" on probe "home" is unhealthy: 3 consecutive unhealthy measurements.',
        ];

        yield 'recovery' => [
            NotificationKind::Recovery,
            NotificationSeverity::Info,
            self::context('recovered after 3 consecutive unhealthy measurements'),
            '[NetPulse] home/wan1 recovered',
            'Connection "wan1" on probe "home" recovered: recovered after 3 consecutive unhealthy measurements.',
        ];
    }

    /**
     * @param array<string, scalar> $context
     */
    #[DataProvider('renderCases')]
    public function testRendersSubjectAndBodyFromRealTemplates(
        NotificationKind $kind,
        NotificationSeverity $severity,
        array $context,
        string $expectedSubject,
        string $expectedBodyContains,
    ): void {
        $notification = $this->renderer()->render($kind, $severity, $context);

        self::assertSame($kind, $notification->kind);
        self::assertSame($severity, $notification->severity);
        self::assertSame($expectedSubject, $notification->subject);
        self::assertStringContainsString($expectedBodyContains, $notification->body);

        self::assertStringNotContainsString($expectedSubject, $notification->body);

        self::assertSame($context, $notification->context);
    }

    public function testBodyContainsTheLatestMeasurementValues(): void
    {
        $notification = $this->renderer()->render(
            NotificationKind::Alert,
            NotificationSeverity::Critical,
            self::context(),
        );

        self::assertStringContainsString('11750000', $notification->body);
        self::assertStringContainsString('2000000', $notification->body);
        self::assertStringContainsString('12.5', $notification->body);
        self::assertStringContainsString('1.5', $notification->body);
    }

    public function testRenderDigestProducesSubjectAndPerConnectionBodyRows(): void
    {
        $digests = ConnectionDigestCollection::of(
            new ConnectionDigest('home', 'wan1', 250_000_000, 50_000_000, 12.3, 0.01, 0.9, 20, 2),
            new ConnectionDigest('home', 'wan2', 80_000_000, 8_000_000, 30.0, 0.05, 0.5, 10, 4),
        );

        $notification = $this->renderer()->renderDigest('daily', $digests);

        self::assertSame(NotificationKind::Digest, $notification->kind);
        self::assertSame(NotificationSeverity::Info, $notification->severity);
        self::assertSame('[NetPulse] daily digest — 2 connections', $notification->subject);

        self::assertStringContainsString('home/wan1', $notification->body);
        self::assertStringContainsString('down 250 Mbps', $notification->body);
        self::assertStringContainsString('up 50 Mbps', $notification->body);
        self::assertStringContainsString('ping 12.3 ms', $notification->body);
        self::assertStringContainsString('loss 1 %', $notification->body);
        self::assertStringContainsString('healthy 90 %', $notification->body);
        self::assertStringContainsString('20 tests, 2 failures', $notification->body);

        self::assertStringContainsString('home/wan2', $notification->body);
        self::assertStringContainsString('down 80 Mbps', $notification->body);
        self::assertStringContainsString('10 tests, 4 failures', $notification->body);

        self::assertStringNotContainsString($notification->subject, $notification->body);

        self::assertSame(['period' => 'daily', 'connections' => 2], $notification->context);
    }

    public function testRenderDigestSingularSubjectForOneConnection(): void
    {
        $digests = ConnectionDigestCollection::of(
            new ConnectionDigest('home', 'wan1', 100_000_000, 10_000_000, 9.0, 0.0, 1.0, 5, 0),
        );

        $notification = $this->renderer()->renderDigest('weekly', $digests);

        self::assertSame('[NetPulse] weekly digest — 1 connection', $notification->subject);
        self::assertSame(['period' => 'weekly', 'connections' => 1], $notification->context);
    }

    /**
     * @return array<string, scalar>
     */
    private static function context(string $reason = '3 consecutive unhealthy measurements'): array
    {
        return [
            'probe' => 'home',
            'connection' => 'wan1',
            'reason' => $reason,
            'downloadBits' => 11_750_000,
            'uploadBits' => 2_000_000,
            'pingMs' => 12.5,
            'packetLoss' => 1.5,
        ];
    }

    private function renderer(): TwigNotificationRenderer
    {
        $twig = new Environment(new FilesystemLoader(dirname(self::TEMPLATES_DIR)));

        return new TwigNotificationRenderer($twig);
    }
}
