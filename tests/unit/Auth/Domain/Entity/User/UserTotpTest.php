<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\Entity\User;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Domain\ValueObject\TotpSecret;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTotpTest extends TestCase
{
    public function testTotpDisabledByDefault(): void
    {
        $user = $this->newUser();

        self::assertFalse($user->hasTotp());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->recoveryCodes());
    }

    public function testEnableTotpStoresSecretAndCodes(): void
    {
        $user = $this->newUser();
        $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

        $user->enableTotp($secret, ['hash-a', 'hash-b', 'hash-c']);

        self::assertTrue($user->hasTotp());
        self::assertNotNull($user->totpSecret());
        self::assertTrue($user->totpSecret()->equals($secret));
        self::assertSame(['hash-a', 'hash-b', 'hash-c'], $user->recoveryCodes());
    }

    public function testDisableTotpClearsSecretAndCodes(): void
    {
        $user = $this->newUser();
        $user->enableTotp(new TotpSecret('JBSWY3DPEHPK3PXP'), ['hash-a', 'hash-b']);

        $user->disableTotp();

        self::assertFalse($user->hasTotp());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->recoveryCodes());
    }

    public function testConsumeRecoveryCodeRemovesExactlyOne(): void
    {
        $user = $this->newUser();
        $user->enableTotp(new TotpSecret('S'), ['hash-a', 'hash-b', 'hash-c']);

        $user->consumeRecoveryCode('hash-b');

        self::assertSame(['hash-a', 'hash-c'], $user->recoveryCodes());
    }

    public function testConsumeUnknownRecoveryCodeIsNoOp(): void
    {
        $user = $this->newUser();
        $user->enableTotp(new TotpSecret('S'), ['hash-a', 'hash-b']);

        $user->consumeRecoveryCode('not-a-real-hash');

        self::assertSame(['hash-a', 'hash-b'], $user->recoveryCodes());
    }

    public function testReplaceRecoveryCodesSwapsTheWholeSet(): void
    {
        $user = $this->newUser();
        $user->enableTotp(new TotpSecret('S'), ['old-1', 'old-2']);

        $user->replaceRecoveryCodes(['new-1', 'new-2', 'new-3']);

        self::assertSame(['new-1', 'new-2', 'new-3'], $user->recoveryCodes());

        self::assertTrue($user->hasTotp());
    }

    private function newUser(): User
    {
        return User::register(
            new UserId('550e8400-e29b-41d4-a716-446655440000'),
            new Email('owner@example.com'),
            HashedPassword::fromHash('hashed-secret'),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
        );
    }
}
