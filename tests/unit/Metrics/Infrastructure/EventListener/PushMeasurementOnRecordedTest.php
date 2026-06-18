<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\EventListener;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Metrics\Application\RemoteWrite\PushMeasurementMessage;
use App\Metrics\Infrastructure\EventListener\PushMeasurementOnRecorded;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PushMeasurementOnRecordedTest extends TestCase
{
    private const string MEASUREMENT_ID = '11111111-1111-4111-8111-111111111111';

    public function testDispatchesPushMessageWhenEnabled(): void
    {
        $dispatched = [];

        $bus = new class($dispatched) implements MessageBusInterface {
            /**
             * @param list<object> $dispatched
             */
            public function __construct(
                private array &$dispatched,
            ) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        $listener = new PushMeasurementOnRecorded($bus, enabled: true);
        $listener($this->event());

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(PushMeasurementMessage::class, $dispatched[0]);
        self::assertSame(self::MEASUREMENT_ID, $dispatched[0]->measurementId);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $dispatched = [];

        $bus = new class($dispatched) implements MessageBusInterface {
            /**
             * @param list<object> $dispatched
             */
            public function __construct(
                private array &$dispatched,
            ) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        $listener = new PushMeasurementOnRecorded($bus, enabled: false);
        $listener($this->event());

        self::assertCount(0, $dispatched);
    }

    private function event(): MeasurementRecorded
    {
        return new MeasurementRecorded(
            new MeasurementId(self::MEASUREMENT_ID),
            new ProbeId('22222222-2222-4222-8222-222222222222'),
            new ConnectionId('33333333-3333-4333-8333-333333333333'),
            new DateTimeImmutable('2026-06-05T10:00:01+00:00'),
        );
    }
}
