<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Id;
use App\Shared\Domain\InvalidId;
use PHPUnit\Framework\TestCase;

final class IdTest extends TestCase
{
    public function testCanCreateValidId(): void
    {
        $validUuid = "550e8400-e29b-41d4-a716-446655440000";
        $id = new Id($validUuid);

        $this->assertEquals($validUuid, $id->toString());
    }

    public function testThrowsExceptionForInvalidId(): void
    {
        $this->expectException(InvalidId::class);

        new Id("invalid-id");
    }

    public function testCanCompareIds(): void
    {
        $uuid = "550e8400-e29b-41d4-a716-446655440000";
        $id1 = new Id($uuid);
        $id2 = new Id($uuid);
        $id3 = new Id("550e8400-e29b-41d4-a716-446655440001");

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
