<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Domain;

use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use PHPUnit\Framework\TestCase;

final class MeasurementIdTest extends TestCase
{
    public function testCanCreateValidMeasurementId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440002";
        $id = new MeasurementId($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testThrowsExceptionForInvalidMeasurementId(): void
    {
        $this->expectException(InvalidId::class);

        new MeasurementId("bad");
    }

    public function testEqualsIsTrueForSameMeasurementId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440002";

        $this->assertTrue((new MeasurementId($uuid))->equals(new MeasurementId($uuid)));
    }

    public function testEqualsIsTypeDistinctFromProbeId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440002";

        $this->assertFalse((new MeasurementId($uuid))->equals(new ProbeId($uuid)));
    }
}
