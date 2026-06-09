<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine\Type;

use App\Auth\Domain\ValueObject\TotpSecret;
use App\Auth\Infrastructure\Security\TotpSecretEncryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use LogicException;

use function is_string;

final class TotpSecretType extends Type
{
    private const string NAME = "totp_secret";

    private static ?TotpSecretEncryptor $encryptor = null;

    public static function setEncryptor(TotpSecretEncryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(["length" => 255]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TotpSecret) {
            return self::encryptor()->encrypt($value->value());
        }

        throw InvalidType::new($value, self::NAME, ["null", TotpSecret::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TotpSecret
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TotpSecret) {
            return $value;
        }

        if (is_string($value)) {
            return new TotpSecret(self::encryptor()->decrypt($value));
        }

        throw ValueNotConvertible::new($value, self::NAME);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    private static function encryptor(): TotpSecretEncryptor
    {
        if (self::$encryptor === null) {
            throw new LogicException(
                "TotpSecretType has no encryptor. RegisterTotpSecretTypeMiddleware must run "
                . "(it is wired as a doctrine.middleware) before a TOTP secret is read or written.",
            );
        }

        return self::$encryptor;
    }
}
