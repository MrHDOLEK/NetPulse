<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\ValueObject\ServerPool;
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

final class ServerPoolType extends Type
{
    public const string NAME = "server_pool";

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

        if (!$value instanceof ServerPool) {
            throw InvalidType::new($value, self::NAME, ["null", ServerPool::class]);
        }

        return json_encode($value->all(), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ServerPool
    {
        if ($value === null || $value === "") {
            return ServerPool::empty();
        }

        if ($value instanceof ServerPool) {
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

        $servers = [];

        foreach ($decoded as $server) {
            if (!is_string($server)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }

            $servers[] = $server;
        }

        return ServerPool::fromList(...$servers);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
