<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Shared\Domain\InvalidId;
use PHPUnit\Framework\TestCase;

final class ConnectionIdTest extends TestCase
{
    public function testCanCreateValidConnectionId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440001";
        $id = new ConnectionId($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testThrowsExceptionForInvalidConnectionId(): void
    {
        $this->expectException(InvalidId::class);

        new ConnectionId("nope");
    }

    public function testEqualsIsTrueForSameConnectionId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440001";

        $this->assertTrue((new ConnectionId($uuid))->equals(new ConnectionId($uuid)));
    }

    public function testEqualsIsTypeDistinctFromMeasurementId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440001";

        $this->assertFalse((new ConnectionId($uuid))->equals(new MeasurementId($uuid)));
    }
}
