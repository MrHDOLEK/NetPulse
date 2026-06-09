<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Domain;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use PHPUnit\Framework\TestCase;

final class ProbeIdTest extends TestCase
{
    public function testCanCreateValidProbeId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440000";
        $id = new ProbeId($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testThrowsExceptionForInvalidProbeId(): void
    {
        $this->expectException(InvalidId::class);

        new ProbeId("not-a-uuid");
    }

    public function testEqualsIsTrueForSameProbeId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440000";

        $this->assertTrue((new ProbeId($uuid))->equals(new ProbeId($uuid)));
    }

    public function testEqualsIsTypeDistinctFromConnectionId(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440000";

        $this->assertFalse((new ProbeId($uuid))->equals(new ConnectionId($uuid)));
    }
}
