<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRole;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Infrastructure\Symfony\Security\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function in_array;

final class SecurityUserTest extends TestCase
{
    public function testGetRolesAppendsRoleUserBaselineToAnAdminOnlyUser(): void
    {
        $security = new SecurityUser($this->userWithRoles(UserRole::Admin));

        $roles = $security->getRoles();

        self::assertTrue(in_array('ROLE_ADMIN', $roles, true), 'stored role preserved');
        self::assertTrue(in_array('ROLE_USER', $roles, true), 'Symfony baseline guaranteed');
        self::assertCount(2, $roles);
    }

    public function testGetRolesDoesNotDuplicateRoleUserWhenAlreadyStored(): void
    {
        $security = new SecurityUser($this->userWithRoles(UserRole::Admin, UserRole::User));

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $security->getRoles());
    }

    private function userWithRoles(UserRole ...$roles): User
    {
        return User::register(
            new UserId('550e8400-e29b-41d4-a716-446655440000'),
            new Email('owner@example.com'),
            HashedPassword::fromHash('hashed-secret'),
            new UserRoleCollection($roles),
            new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
        );
    }
}
