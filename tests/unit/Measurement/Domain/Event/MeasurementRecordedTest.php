<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain\Event;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MeasurementRecordedTest extends TestCase
{
    public function testCarriesIdentitiesAndOccurredAt(): void
    {
        $occurredAt = new DateTimeImmutable('2026-06-05T10:00:00Z');

        $event = new MeasurementRecorded(
            new MeasurementId('11111111-1111-4111-8111-111111111111'),
            new ProbeId('22222222-2222-4222-8222-222222222222'),
            new ConnectionId('33333333-3333-4333-8333-333333333333'),
            $occurredAt,
        );

        $this->assertSame('11111111-1111-4111-8111-111111111111', $event->measurementId->toString());
        $this->assertSame('22222222-2222-4222-8222-222222222222', $event->probeId->toString());
        $this->assertSame('33333333-3333-4333-8333-333333333333', $event->connectionId->toString());
        $this->assertSame($occurredAt, $event->occurredAt);
    }
}
