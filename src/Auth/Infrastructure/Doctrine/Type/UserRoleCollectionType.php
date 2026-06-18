<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine\Type;

use App\Auth\Domain\Entity\User\UserRoleCollection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use ValueError;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class UserRoleCollectionType extends Type
{
    public const string NAME = 'user_role_collection';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof UserRoleCollection) {
            throw InvalidType::new($value, self::NAME, ['null', UserRoleCollection::class]);
        }

        return json_encode($value->toStringArray(), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): UserRoleCollection
    {
        if ($value === null || $value === '') {
            return new UserRoleCollection([]);
        }

        if ($value instanceof UserRoleCollection) {
            return $value;
        }

        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }

        if (!is_array($decoded)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        $roles = [];

        foreach ($decoded as $role) {
            if (!is_string($role)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }

            $roles[] = $role;
        }

        try {
            return UserRoleCollection::fromStrings($roles);
        } catch (ValueError $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
