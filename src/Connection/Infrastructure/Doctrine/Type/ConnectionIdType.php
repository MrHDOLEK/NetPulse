<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\ValueObject\ConnectionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;

use function is_string;

final class ConnectionIdType extends GuidType
{
    private const string NAME = 'connection_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof ConnectionId) {
            throw InvalidType::new($value, self::NAME, [ConnectionId::class, 'null']);
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ConnectionId
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return new ConnectionId($value);
    }
}
