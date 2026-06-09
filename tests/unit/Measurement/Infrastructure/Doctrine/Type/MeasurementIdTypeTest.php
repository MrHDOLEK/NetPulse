<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Infrastructure\Doctrine\Type;

use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Measurement\Infrastructure\Doctrine\Type\MeasurementIdType;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

final class MeasurementIdTypeTest extends TestCase
{
    private MeasurementIdType $type;
    private SqlitePlatform $platform;

    protected function setUp(): void
    {
        $this->type = new MeasurementIdType();
        $this->platform = new SqlitePlatform();
    }

    public function testConvertsMeasurementIdToDatabaseString(): void
    {
        $id = new MeasurementId("11111111-1111-4111-8111-111111111111");

        $this->assertSame(
            "11111111-1111-4111-8111-111111111111",
            $this->type->convertToDatabaseValue($id, $this->platform),
        );
    }

    public function testConvertsDatabaseStringToMeasurementId(): void
    {
        $id = $this->type->convertToPHPValue("11111111-1111-4111-8111-111111111111", $this->platform);

        $this->assertInstanceOf(MeasurementId::class, $id);
        $this->assertSame("11111111-1111-4111-8111-111111111111", $id->toString());
    }

    public function testNullRoundTripsAsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRejectsNonStringDatabaseValue(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue(12345, $this->platform);
    }
}
