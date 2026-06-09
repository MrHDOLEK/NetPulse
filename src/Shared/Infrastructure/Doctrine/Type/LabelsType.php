<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class LabelsType extends Type
{
    public const string NAME = "labels";

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

        if (!$value instanceof Labels) {
            throw InvalidType::new($value, self::NAME, ["null", Labels::class]);
        }

        return json_encode($value->all(), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Labels
    {
        if ($value === null || $value === "") {
            return Labels::empty();
        }

        if ($value instanceof Labels) {
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

        $labels = [];

        foreach ($decoded as $key => $label) {
            if (!is_string($key) || !is_string($label)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }

            $labels[$key] = $label;
        }

        return Labels::fromArray($labels);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
