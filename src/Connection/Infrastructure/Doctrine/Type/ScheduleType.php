<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Doctrine\Type;

use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\ValueObject\Schedule;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use ValueError;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ScheduleType extends Type
{
    public const string NAME = 'schedule';

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

        if (!$value instanceof Schedule) {
            throw InvalidType::new($value, self::NAME, ['null', Schedule::class]);
        }

        return json_encode($this->toPayload($value), JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Schedule
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Schedule) {
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

        return $this->fromPayload($value, $decoded);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{mode: string, cronExpressions: list<string>, testsPerDay: int, jitterSeconds: int}
     */
    private function toPayload(Schedule $schedule): array
    {
        return [
            'mode' => $schedule->mode()->value,
            'cronExpressions' => $schedule->cronExpressions(),
            'testsPerDay' => $schedule->testsPerDay(),
            'jitterSeconds' => $schedule->jitterSeconds(),
        ];
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function fromPayload(string $value, array $decoded): Schedule
    {
        $rawMode = $decoded['mode'] ?? null;

        if (!is_string($rawMode)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            $mode = ScheduleMode::from($rawMode);
        } catch (ValueError $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }

        return match ($mode) {
            ScheduleMode::Cron => Schedule::cron(...$this->cronExpressions($value, $decoded)),
            ScheduleMode::Even => Schedule::even(
                $this->intField($value, $decoded, 'testsPerDay'),
                $this->intField($value, $decoded, 'jitterSeconds'),
            ),
        };
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return list<string>
     */
    private function cronExpressions(string $value, array $decoded): array
    {
        $raw = $decoded['cronExpressions'] ?? null;

        if (!is_array($raw)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        $expressions = [];

        foreach ($raw as $expression) {
            if (!is_string($expression)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }

            $expressions[] = $expression;
        }

        return $expressions;
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
