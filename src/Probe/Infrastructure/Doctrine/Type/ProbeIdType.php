<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Doctrine\Type;

use App\Probe\Domain\ValueObject\ProbeId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

use function is_string;

final class ProbeIdType extends Type
{
    private const string NAME = "probe_id";

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(["length" => 36]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProbeId) {
            return $value->toString();
        }

        throw InvalidType::new($value, self::NAME, ["null", ProbeId::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProbeId
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProbeId) {
            return $value;
        }

        if (is_string($value)) {
            return new ProbeId($value);
        }

        throw ValueNotConvertible::new($value, self::NAME);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
