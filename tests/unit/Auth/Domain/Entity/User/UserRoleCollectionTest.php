<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\Entity\User;

use App\Auth\Domain\Entity\User\UserRole;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use PHPUnit\Framework\TestCase;
use ValueError;

final class UserRoleCollectionTest extends TestCase
{
    public function testEnumValuesAreTheExactSymfonyStrings(): void
    {
        self::assertSame('ROLE_ADMIN', UserRole::Admin->value);
        self::assertSame('ROLE_USER', UserRole::User->value);
    }

    public function testFromStringsBuildsTypedCollection(): void
    {
        $collection = UserRoleCollection::fromStrings(['ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN'], $collection->toStringArray());
        self::assertTrue($collection->contains(UserRole::Admin));
        self::assertFalse($collection->contains(UserRole::User));
    }

    public function testUnknownRoleStringIsRejectedFailClosed(): void
    {
        $this->expectException(ValueError::class);

        UserRoleCollection::fromStrings(['ROLE_ADMIN', 'ROLE_BOGUS']);
    }

    public function testDuplicatesAreCollapsed(): void
    {
        $collection = new UserRoleCollection([UserRole::Admin, UserRole::Admin, UserRole::User]);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $collection->toStringArray());
        self::assertCount(2, $collection);
    }

    public function testWithRoleReturnsANewCollectionAndIsImmutable(): void
    {
        $original = new UserRoleCollection([UserRole::Admin]);
        $extended = $original->withRole(UserRole::User);

        self::assertSame(['ROLE_ADMIN'], $original->toStringArray());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $extended->toStringArray());
    }

    public function testEmptyCollection(): void
    {
        $collection = new UserRoleCollection([]);

        self::assertTrue($collection->isEmpty());
        self::assertSame([], $collection->toStringArray());
    }
}
