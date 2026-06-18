<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application;

use App\Auth\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Auth\Application\Command\ResetPassword\ResetPasswordHandler;
use App\Auth\Application\WeakPassword;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Tests\Support\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class ResetPasswordHandlerTest extends TestCase
{
    private const USER_ID = '0190a000-0000-7000-8000-000000000002';

    public function testReplacesStoredHashWithVerifiableHashForExistingEmail(): void
    {
        $repository = new InMemoryUserRepository();
        $hasher = $this->hasher();

        $existingHash = $hasher->hash('old-strong-password');
        $repository->save(User::register(
            new UserId(self::USER_ID),
            new Email('admin@example.com'),
            HashedPassword::fromHash($existingHash),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            new MockClock('2026-06-06T10:00:00+00:00')->now(),
        ));

        $handler = new ResetPasswordHandler($repository, $this->factory());

        $handler(new ResetPasswordCommand('admin@example.com', 'brand-new-password'));

        self::assertSame(1, $repository->count());

        $stored = $repository->byEmail(new Email('admin@example.com'));
        self::assertInstanceOf(User::class, $stored);
        self::assertNotSame($existingHash, $stored->password()->value());
        self::assertTrue($hasher->verify($stored->password()->value(), 'brand-new-password'));
        self::assertFalse($hasher->verify($stored->password()->value(), 'old-strong-password'));
    }

    public function testThrowsNotFoundForUnknownEmailAndSavesNothing(): void
    {
        $repository = new InMemoryUserRepository();

        $handler = new ResetPasswordHandler($repository, $this->factory());

        try {
            $handler(new ResetPasswordCommand('nobody@example.com', 'brand-new-password'));
            self::fail('Expected UserNotFound to be thrown.');
        } catch (UserNotFound) {
        }

        self::assertSame(0, $repository->count());
    }

    public function testRejectsWeakPasswordAndLeavesStoredHashUntouched(): void
    {
        $repository = new InMemoryUserRepository();
        $hasher = $this->hasher();

        $existingHash = $hasher->hash('old-strong-password');
        $repository->save(User::register(
            new UserId(self::USER_ID),
            new Email('admin@example.com'),
            HashedPassword::fromHash($existingHash),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            new MockClock('2026-06-06T10:00:00+00:00')->now(),
        ));

        $handler = new ResetPasswordHandler($repository, $this->factory());

        $this->expectException(WeakPassword::class);

        try {
            $handler(new ResetPasswordCommand('admin@example.com', 'short'));
        } finally {
            $stored = $repository->byEmail(new Email('admin@example.com'));
            self::assertInstanceOf(User::class, $stored);
            self::assertSame($existingHash, $stored->password()->value());
        }
    }

    private function factory(): PasswordHasherFactory
    {
        return new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => [
                'algorithm' => 'auto',
                'cost' => 4,
                'time_cost' => 3,
                'memory_cost' => 10,
            ],
        ]);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->factory()->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
    }
}
