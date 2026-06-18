<?php

declare(strict_types=1);

namespace App\Auth\Domain\Entity\User;

use App\Shared\Domain\Collection;

use function array_map;
use function in_array;

/**
 * @extends Collection<UserRole>
 */
final class UserRoleCollection extends Collection
{
    /**
     * @param list<UserRole> $roles
     */
    public function __construct(array $roles)
    {
        parent::__construct(self::dedupe($roles));
    }

    /**
     * @param list<string> $values
     */
    public static function fromStrings(array $values): self
    {
        return new self(array_map(static fn(string $value): UserRole => UserRole::from($value), $values));
    }

    /**
     * @return list<string>
     */
    public function toStringArray(): array
    {
        return array_map(static fn(UserRole $role): string => $role->value, $this->toArray());
    }

    public function contains(UserRole $role): bool
    {
        return in_array($role, $this->toArray(), true);
    }

    public function withRole(UserRole $role): self
    {
        return new self([...$this->toArray(), $role]);
    }

    /**
     * @param list<UserRole> $roles
     *
     * @return list<UserRole>
     */
    private static function dedupe(array $roles): array
    {
        $seen = [];
        $unique = [];

        foreach ($roles as $role) {
            if (isset($seen[$role->value])) {
                continue;
            }

            $seen[$role->value] = true;
            $unique[] = $role;
        }

        return $unique;
    }
}
