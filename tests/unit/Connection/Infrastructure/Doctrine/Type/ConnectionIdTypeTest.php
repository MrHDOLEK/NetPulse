<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Infrastructure\Doctrine\Type\ConnectionIdType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

final class ConnectionIdTypeTest extends TestCase
{
    private const string UUID = "44444444-4444-7444-8444-444444444444";

    private ConnectionIdType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new ConnectionIdType();
        $this->platform = new SQLitePlatform();
    }

    public function testConvertsConnectionIdToDatabaseString(): void
    {
        $value = $this->type->convertToDatabaseValue(new ConnectionId(self::UUID), $this->platform);

        $this->assertSame(self::UUID, $value);
    }

    public function testConvertsNullToDatabaseNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertsDatabaseStringToConnectionId(): void
    {
        $connectionId = $this->type->convertToPHPValue(self::UUID, $this->platform);

        $this->assertInstanceOf(ConnectionId::class, $connectionId);
        $this->assertSame(self::UUID, $connectionId->toString());
    }

    public function testConvertsNullToPhpNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRejectsNonStringDatabaseValue(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue(123, $this->platform);
    }

    public function testRejectsNonConnectionIdOnWrite(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue("not-an-id", $this->platform);
    }

    public function testHasName(): void
    {
        $this->assertSame("connection_id", $this->type->getName());
    }
}
