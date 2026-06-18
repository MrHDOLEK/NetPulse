<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Service\AlertDecider;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlertDeciderTest extends TestCase
{
    private const int THRESHOLD = 3;

    /**
     * @return iterable<string, array{HealthHistory, int, ?NotificationKind}>
     */
    public static function provideHistories(): iterable
    {
        yield 'empty history → none' => [HealthHistory::empty(), self::THRESHOLD, null];

        yield 'below threshold (2 unhealthy) → none' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::healthy(2)),
            self::THRESHOLD,
            null,
        ];

        yield 'alert exactly at N (window preceded by healthy)' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::unhealthy(2), self::healthy(3)),
            self::THRESHOLD,
            NotificationKind::Alert,
        ];

        yield 'alert exactly at N from the start (history length == N)' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::unhealthy(2)),
            self::THRESHOLD,
            NotificationKind::Alert,
        ];

        yield 'no re-alert at N+1 (debounce)' => [
            self::history(
                self::unhealthy(0),
                self::unhealthy(1),
                self::unhealthy(2),
                self::unhealthy(3),
                self::healthy(4),
            ),
            self::THRESHOLD,
            null,
        ];

        yield 'no re-alert at N+1 from the start (debounce)' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::unhealthy(2), self::unhealthy(3)),
            self::THRESHOLD,
            null,
        ];

        yield 'mixed failed + breach all count as unhealthy → alert at N' => [
            self::history(self::failed(0), self::unhealthy(1), self::failed(2), self::healthy(3)),
            self::THRESHOLD,
            NotificationKind::Alert,
        ];

        yield 'recovery: healthy newest after a full alert window' => [
            self::history(self::healthy(0), self::unhealthy(1), self::unhealthy(2), self::unhealthy(3)),
            self::THRESHOLD,
            NotificationKind::Recovery,
        ];

        yield 'recovery: healthy newest after MORE than N unhealthy' => [
            self::history(self::healthy(0), self::failed(1), self::unhealthy(2), self::failed(3), self::unhealthy(4)),
            self::THRESHOLD,
            NotificationKind::Recovery,
        ];

        yield 'no recovery: healthy newest after only 2 unhealthy (< N)' => [
            self::history(self::healthy(0), self::unhealthy(1), self::unhealthy(2), self::healthy(3)),
            self::THRESHOLD,
            null,
        ];

        yield 'no recovery: healthy newest, all healthy history' => [
            self::history(self::healthy(0), self::healthy(1), self::healthy(2)),
            self::THRESHOLD,
            null,
        ];

        yield 'null verdict does not count as unhealthy → none at would-be window' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::nullVerdict(2)),
            self::THRESHOLD,
            null,
        ];

        yield 'no recovery: window broken by a null verdict before newest' => [
            self::history(
                self::healthy(0),
                self::nullVerdict(1),
                self::unhealthy(2),
                self::unhealthy(3),
                self::unhealthy(4),
            ),
            self::THRESHOLD,
            null,
        ];

        yield 'threshold 1: single unhealthy → alert' => [
            self::history(self::unhealthy(0), self::healthy(1)),
            1,
            NotificationKind::Alert,
        ];

        yield 'threshold 1: two consecutive unhealthy → no re-alert' => [
            self::history(self::unhealthy(0), self::unhealthy(1), self::healthy(2)),
            1,
            null,
        ];
    }

    #[DataProvider('provideHistories')]
    public function testDecide(HealthHistory $history, int $threshold, ?NotificationKind $expectedKind): void
    {
        $decision = new AlertDecider()->decide($history, $threshold);

        self::assertSame($expectedKind, $decision->kind());
        self::assertSame($expectedKind !== null, $decision->shouldNotify());
    }

    public function testAlertReasonMentionsThreshold(): void
    {
        $history = self::history(self::unhealthy(0), self::unhealthy(1), self::unhealthy(2));

        $decision = new AlertDecider()->decide($history, self::THRESHOLD);

        self::assertSame(NotificationKind::Alert, $decision->kind());
        self::assertStringContainsString('3 consecutive unhealthy', $decision->reason);
    }

    public function testRecoveryReasonMentionsRecovered(): void
    {
        $history = self::history(self::healthy(0), self::unhealthy(1), self::unhealthy(2), self::unhealthy(3));

        $decision = new AlertDecider()->decide($history, self::THRESHOLD);

        self::assertSame(NotificationKind::Recovery, $decision->kind());
        self::assertStringContainsString('recovered', $decision->reason);
    }

    private static function history(HealthSample ...$samples): HealthHistory
    {
        return HealthHistory::of(...$samples);
    }

    private static function healthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), true);
    }

    private static function unhealthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), false);
    }

    private static function nullVerdict(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), null);
    }

    private static function failed(int $index): HealthSample
    {
        return HealthSample::failed(self::at($index));
    }

    private static function at(int $index): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-06 12:00:00')->modify('-' . ($index * 60) . ' seconds');
    }
}
