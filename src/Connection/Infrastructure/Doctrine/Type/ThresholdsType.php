<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\ValueObject\Thresholds;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ThresholdsType extends Type
{
    public const string NAME = "thresholds";

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

        if (!$value instanceof Thresholds) {
            throw InvalidType::new($value, self::NAME, ["null", Thresholds::class]);
        }

        return json_encode($this->toPayload($value), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Thresholds
    {
        if ($value === null || $value === "") {
            return null;
        }

        if ($value instanceof Thresholds) {
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

        return Thresholds::of(
            $this->floatField($value, $decoded, "minDownloadRatio"),
            $this->floatField($value, $decoded, "minUploadRatio"),
            $this->nullableFloatField($value, $decoded, "maxPingMs"),
            $this->nullableFloatField($value, $decoded, "maxJitterMs"),
            $this->nullableFloatField($value, $decoded, "maxPacketLossRatio"),
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{minDownloadRatio: float, minUploadRatio: float, maxPingMs: ?float, maxJitterMs: ?float, maxPacketLossRatio: ?float}
     */
    private function toPayload(Thresholds $thresholds): array
    {
        return [
            "minDownloadRatio" => $thresholds->minDownloadRatio(),
            "minUploadRatio" => $thresholds->minUploadRatio(),
            "maxPingMs" => $thresholds->maxPingMs(),
            "maxJitterMs" => $thresholds->maxJitterMs(),
            "maxPacketLossRatio" => $thresholds->maxPacketLossRatio(),
        ];
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function floatField(string $value, array $decoded, string $field): float
    {
        $raw = $decoded[$field] ?? null;

        if (is_int($raw)) {
            return (float)$raw;
        }

        if (!is_float($raw)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return $raw;
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function nullableFloatField(string $value, array $decoded, string $field): ?float
    {
        $raw = $decoded[$field] ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_int($raw)) {
            return (float)$raw;
        }

        if (!is_float($raw)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return $raw;
    }
}
