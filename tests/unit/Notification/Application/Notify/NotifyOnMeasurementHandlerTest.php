<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Notify;

use App\Connection\Domain\ConnectionCollection;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Notification\Application\Command\Notify\NotifyOnMeasurementCommand;
use App\Notification\Application\Command\Notify\NotifyOnMeasurementHandler;
use App\Notification\Application\Digest\ConnectionDigestCollection;
use App\Notification\Application\NotificationHealthRepository;
use App\Notification\Application\NotificationRenderer;
use App\Notification\Application\NotificationSettingsProvider;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\NotificationDispatcher;
use App\Notification\Domain\Service\AlertDecider;
use App\Notification\Domain\ValueObject\Notification;
use App\Notification\Domain\ValueObject\NotificationSettings;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeCollection;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use App\Scheduling\Domain\ValueObject\HealthSample;
use App\Shared\Domain\ValueObject\Labels;
use App\Tests\Support\MeasurementMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

final class NotifyOnMeasurementHandlerTest extends TestCase
{
    private const int THRESHOLD = 3;
    private const string MEASUREMENT_ID = '11111111-1111-4111-8111-111111111111';
    private const string PROBE_ID = '22222222-2222-4222-8222-222222222222';
    private const string CONNECTION_ID = '33333333-3333-4333-8333-333333333333';

    public function testReachingThresholdUnhealthySendsExactlyOneAlert(): void
    {
        $dispatcher = $this->recordingDispatcher();

        $history = HealthHistory::of(self::unhealthy(0), self::unhealthy(1), self::unhealthy(2), self::healthy(3));

        $this->handler($history, $dispatcher)(new NotifyOnMeasurementCommand(self::MEASUREMENT_ID));

        self::assertCount(1, $dispatcher->sent);
        self::assertSame(NotificationKind::Alert, $dispatcher->sent[0]->kind);
        self::assertSame('wan1', $dispatcher->sent[0]->context['connection']);
        self::assertSame('home', $dispatcher->sent[0]->context['probe']);
    }

    public function testOneMoreUnhealthyAfterAlertSendsNothing(): void
    {
        $dispatcher = $this->recordingDispatcher();

        $history = HealthHistory::of(
            self::unhealthy(0),
            self::unhealthy(1),
            self::unhealthy(2),
            self::unhealthy(3),
            self::healthy(4),
        );

        $this->handler($history, $dispatcher)(new NotifyOnMeasurementCommand(self::MEASUREMENT_ID));

        self::assertCount(0, $dispatcher->sent);
    }

    public function testHealthyAfterAlertWindowSendsExactlyOneRecovery(): void
    {
        $dispatcher = $this->recordingDispatcher();

        $history = HealthHistory::of(self::healthy(0), self::unhealthy(1), self::unhealthy(2), self::unhealthy(3));

        $this->handler($history, $dispatcher)(new NotifyOnMeasurementCommand(self::MEASUREMENT_ID));

        self::assertCount(1, $dispatcher->sent);
        self::assertSame(NotificationKind::Recovery, $dispatcher->sent[0]->kind);
    }

    public function testBelowThresholdSendsNothing(): void
    {
        $dispatcher = $this->recordingDispatcher();

        $history = HealthHistory::of(self::unhealthy(0), self::unhealthy(1), self::healthy(2));

        $this->handler($history, $dispatcher)(new NotifyOnMeasurementCommand(self::MEASUREMENT_ID));

        self::assertCount(0, $dispatcher->sent);
    }

    private static function healthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), true);
    }

    private static function unhealthy(int $index): HealthSample
    {
        return HealthSample::completed(self::at($index), false);
    }

    private static function at(int $index): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-06 12:00:00')->modify('-' . ($index * 60) . ' seconds');
    }

    private function handler(
        HealthHistory $history,
        RecordingNotificationDispatcher $dispatcher,
    ): NotifyOnMeasurementHandler {
        return new NotifyOnMeasurementHandler(
            $this->measurementRepository(),
            $this->connectionRepository(),
            $this->probeRepository(),
            $this->readModelReturning($history),
            new AlertDecider(),
            $this->renderer(),
            $dispatcher,
            $this->settingsProvider(),
            new NullLogger(),
        );
    }

    private function settingsProvider(): NotificationSettingsProvider
    {
        return new class(self::THRESHOLD) implements NotificationSettingsProvider {
            public function __construct(
                private readonly int $threshold,
            ) {}

            public function current(): NotificationSettings
            {
                return new NotificationSettings(
                    enabled: true,
                    consecutiveThreshold: $this->threshold,
                    channels: [],
                    emailRecipients: [],
                    emailDsn: '',
                    chatDsn: '',
                    webhookUrl: '',
                );
            }
        };
    }

    private function renderer(): NotificationRenderer
    {
        return new class() implements NotificationRenderer {
            public function render(NotificationKind $kind, NotificationSeverity $severity, array $context): Notification
            {
                return new Notification($kind, $severity, 'subject', 'body', $context);
            }

            public function renderDigest(string $period, ConnectionDigestCollection $digests): Notification
            {
                return new Notification(NotificationKind::Digest, NotificationSeverity::Info, 'subject', 'body', [
                    'period' => $period,
                    'connections' => $digests->count(),
                ]);
            }
        };
    }

    private function recordingDispatcher(): RecordingNotificationDispatcher
    {
        return new RecordingNotificationDispatcher();
    }

    private function readModelReturning(HealthHistory $history): NotificationHealthRepository
    {
        return new class($history) implements NotificationHealthRepository {
            public function __construct(
                private readonly HealthHistory $history,
            ) {}

            public function forConnection(ConnectionId $connectionId, int $limit): HealthHistory
            {
                return $this->history;
            }
        };
    }

    private function measurementRepository(): MeasurementRepository
    {
        $measurement = MeasurementMother::fromOoklaArray(
            [
                'type' => 'result',
                'timestamp' => '2026-06-05T10:00:01Z',
                'ping' => ['jitter' => 0.5, 'latency' => 12.5, 'low' => 11.0, 'high' => 14.0],
                'download' => ['bandwidth' => 11_750_000, 'bytes' => 50_000_000, 'elapsed' => 5000],
                'upload' => ['bandwidth' => 2_000_000, 'bytes' => 10_000_000, 'elapsed' => 5000],
                'packetLoss' => 0.0,
                'server' => ['id' => 1, 'name' => 'S', 'location' => 'L', 'host' => 'h', 'ip' => '1.2.3.4'],
                'result' => ['url' => 'https://x'],
            ],
            self::MEASUREMENT_ID,
            self::PROBE_ID,
            self::CONNECTION_ID,
            true,
            new MockClock('2026-06-06T10:00:00+00:00')->now(),
        );

        return new class($measurement) implements MeasurementRepository {
            public function __construct(
                private readonly Measurement $measurement,
            ) {}

            public function save(Measurement $measurement): void {}

            public function get(MeasurementId $id): Measurement
            {
                return $this->measurement;
            }

            public function find(MeasurementId $id): ?Measurement
            {
                return $this->measurement;
            }
        };
    }

    private function connectionRepository(): ConnectionRepository
    {
        $connection = new Connection(
            new ConnectionId(self::CONNECTION_ID),
            new ProbeId(self::PROBE_ID),
            'wan1',
            'Orange Polska',
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::fromList('12746'),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        return new class($connection) implements ConnectionRepository {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function save(Connection $connection): void {}

            public function delete(Connection $connection): void {}

            public function get(ConnectionId $connectionId): Connection
            {
                return $this->connection;
            }

            public function find(ConnectionId $connectionId): ?Connection
            {
                return $this->connection;
            }

            public function byProbe(ProbeId $probeId): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }

            public function allEnabled(): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }

            public function all(): ConnectionCollection
            {
                return ConnectionCollection::of($this->connection);
            }
        };
    }

    private function probeRepository(): ProbeRepository
    {
        $probe = new Probe(
            new ProbeId(self::PROBE_ID),
            'home',
            Labels::fromArray(['site' => 'warsaw']),
            'hash',
            true,
            new DateTimeImmutable('2026-06-06T10:00:00+00:00'),
        );

        return new class($probe) implements ProbeRepository {
            public function __construct(
                private readonly Probe $probe,
            ) {}

            public function save(Probe $probe): void {}

            public function delete(Probe $probe): void {}

            public function get(ProbeId $id): Probe
            {
                return $this->probe;
            }

            public function find(ProbeId $id): ?Probe
            {
                return $this->probe;
            }

            public function all(): ProbeCollection
            {
                return ProbeCollection::of($this->probe);
            }
        };
    }
}

final class RecordingNotificationDispatcher implements NotificationDispatcher
{
    /** @var list<Notification> */
    public array $sent = [];

    public function send(Notification $notification): void
    {
        $this->sent[] = $notification;
    }
}
