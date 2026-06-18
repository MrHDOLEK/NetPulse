<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Infrastructure\Doctrine\Type;

use App\Probe\Domain\ValueObject\ProbeId;
use App\Probe\Infrastructure\Doctrine\Type\ProbeIdType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

final class ProbeIdTypeTest extends TestCase
{
    private const string UUID = '22222222-2222-4222-8222-222222222222';

    private ProbeIdType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new ProbeIdType();
        $this->platform = new SQLitePlatform();
    }

    public function testConvertsProbeIdToDatabaseString(): void
    {
        $value = $this->type->convertToDatabaseValue(new ProbeId(self::UUID), $this->platform);

        $this->assertSame(self::UUID, $value);
    }

    public function testConvertsNullToDatabaseNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertsDatabaseStringToProbeId(): void
    {
        $probeId = $this->type->convertToPHPValue(self::UUID, $this->platform);

        $this->assertInstanceOf(ProbeId::class, $probeId);
        $this->assertSame(self::UUID, $probeId->toString());
    }

    public function testConvertsNullToPhpNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testReturnsProbeIdUnchangedOnRead(): void
    {
        $probeId = new ProbeId(self::UUID);

        $this->assertSame($probeId, $this->type->convertToPHPValue($probeId, $this->platform));
    }

    public function testRejectsNonStringDatabaseValue(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue(123, $this->platform);
    }

    public function testRejectsNonProbeIdOnWrite(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue('not-an-id', $this->platform);
    }

    public function testHasName(): void
    {
        $this->assertSame('probe_id', $this->type->getName());
    }
}
