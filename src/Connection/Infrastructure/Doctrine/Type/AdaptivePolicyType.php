<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\ValueObject\AdaptivePolicy;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class AdaptivePolicyType extends Type
{
    public const string NAME = 'adaptive_policy';

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

        if (!$value instanceof AdaptivePolicy) {
            throw InvalidType::new($value, self::NAME, ['null', AdaptivePolicy::class]);
        }

        return json_encode($this->toPayload($value), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AdaptivePolicy
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof AdaptivePolicy) {
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

        return AdaptivePolicy::of(
            $this->intField($value, $decoded, 'adaptiveIntervalSeconds'),
            $this->intField($value, $decoded, 'recoveryHealthyCount'),
            $this->intField($value, $decoded, 'maxConsecutiveFailures'),
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{adaptiveIntervalSeconds: int, recoveryHealthyCount: int, maxConsecutiveFailures: int}
     */
    private function toPayload(AdaptivePolicy $policy): array
    {
        return [
            'adaptiveIntervalSeconds' => $policy->adaptiveIntervalSeconds(),
            'recoveryHealthyCount' => $policy->recoveryHealthyCount(),
            'maxConsecutiveFailures' => $policy->maxConsecutiveFailures(),
        ];
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function intField(string $value, array $decoded, string $field): int
    {
        $raw = $decoded[$field] ?? null;

        if (!is_int($raw)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return $raw;
    }
}
