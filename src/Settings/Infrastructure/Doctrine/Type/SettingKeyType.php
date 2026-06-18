<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Doctrine\Type;

use App\Settings\Domain\SettingKey;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use ValueError;

use function is_string;

final class SettingKeyType extends Type
{
    private const string NAME = 'setting_key';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 100]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if ($value instanceof SettingKey) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        throw InvalidType::new($value, self::NAME, ['string', SettingKey::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): SettingKey
    {
        if ($value instanceof SettingKey) {
            return $value;
        }

        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return SettingKey::from($value);
        } catch (ValueError $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
