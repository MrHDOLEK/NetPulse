<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine\Type;

use App\Auth\Domain\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

use function is_string;

final class EmailType extends Type
{
    private const string NAME = 'email';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Email) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', Email::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Email) {
            return $value;
        }

        if (is_string($value)) {
            return new Email($value);
        }

        throw ValueNotConvertible::new($value, self::NAME);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
