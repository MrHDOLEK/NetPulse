<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

final readonly class ConnectionDegradedIndex
{
    /**
     * @param array<string, bool> $degradedByKey
     */
    public function __construct(
        private array $degradedByKey,
    ) {}

    public static function key(string $probeName, string $connectionName): string
    {
        return $probeName . "\0" . $connectionName;
    }

    public function isDegraded(string $probeName, string $connectionName): bool
    {
        return $this->degradedByKey[self::key($probeName, $connectionName)] ?? false;
    }
}
