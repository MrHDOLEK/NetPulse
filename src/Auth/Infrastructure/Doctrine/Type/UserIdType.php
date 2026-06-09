<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine\Type;

use App\Auth\Domain\Entity\User\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;

use function is_string;

final class UserIdType extends GuidType
{
    private const string NAME = "user_id";

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof UserId) {
            throw InvalidType::new($value, self::NAME, [UserId::class, "null"]);
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return new UserId($value);
    }
}
