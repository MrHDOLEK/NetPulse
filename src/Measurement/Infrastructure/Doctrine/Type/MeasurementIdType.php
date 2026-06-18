<?php

declare(strict_types=1);

namespace App\Measurement\Infrastructure\Doctrine\Type;

use App\Measurement\Domain\ValueObject\MeasurementId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

use function is_string;

final class MeasurementIdType extends Type
{
    public const string NAME = 'measurement_id';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MeasurementId) {
            return $value->toString();
        }

        throw ValueNotConvertible::new($value, self::NAME);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MeasurementId
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return new MeasurementId($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
