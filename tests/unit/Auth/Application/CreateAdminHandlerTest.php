<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application;

use App\Auth\Application\Command\CreateAdmin\AdminAlreadyExists;
use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Application\Command\CreateAdmin\CreateAdminHandler;
use App\Auth\Application\WeakPassword;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Shared\Application\Service\IdGeneratorInterface;
use App\Shared\Domain\Id;
use App\Tests\Support\InMemoryUserRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use function in_array;

final class CreateAdminHandlerTest extends TestCase
{
    private const NEW_ID = '0190a000-0000-7000-8000-000000000001';
    private const CREATED_AT = '2026-06-06T10:00:00+00:00';

    public function testCreatesSingleAdminWithVerifiableHashAndAdminRole(): void
    {
        $repository = new InMemoryUserRepository();
        $hasher = $this->hasher();

        $handler = new CreateAdminHandler($repository, $this->factory(), $this->idGenerator(), $this->clock());

        $handler(new CreateAdminCommand('admin@example.com', 'super-secret-password'));

        self::assertSame(1, $repository->count());

        $stored = $repository->byEmail(new Email('admin@example.com'));
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('admin@example.com', $stored->email()->value());
        self::assertTrue($stored->id()->equals(new UserId(self::NEW_ID)));
        self::assertTrue(in_array('ROLE_ADMIN', $stored->roles()->toStringArray(), true));

        self::assertNotSame('super-secret-password', $stored->password()->value());
        self::assertTrue($hasher->verify($stored->password()->value(), 'super-secret-password'));
    }

    public function testRejectsPasswordShorterThanTwelveCharactersAndSavesNothing(): void
    {
        $repository = new InMemoryUserRepository();

        $handler = new CreateAdminHandler($repository, $this->factory(), $this->idGenerator(), $this->clock());

        try {
            $handler(new CreateAdminCommand('admin@example.com', 'short'));
            self::fail('Expected WeakPassword to be thrown.');
        } catch (WeakPassword $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
        }

        self::assertSame(0, $repository->count());
    }

    public function testRejectsDuplicateEmailAndDoesNotDuplicate(): void
    {
        $repository = new InMemoryUserRepository();
        $repository->save(User::register(
            new UserId('0190a000-0000-7000-8000-0000000000ff'),
            new Email('admin@example.com'),
            HashedPassword::fromHash('pre-existing-hash'),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            $this->clock()->now(),
        ));

        $handler = new CreateAdminHandler($repository, $this->factory(), $this->idGenerator(), $this->clock());

        try {
            $handler(new CreateAdminCommand('admin@example.com', 'another-strong-password'));
            self::fail('Expected AdminAlreadyExists to be thrown.');
        } catch (AdminAlreadyExists) {
        }

        self::assertSame(1, $repository->count());
        $stored = $repository->byEmail(new Email('admin@example.com'));
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('pre-existing-hash', $stored->password()->value());
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

    private function idGenerator(): IdGeneratorInterface
    {
        return new class(self::NEW_ID) implements IdGeneratorInterface {
            public function __construct(
                private readonly string $id,
            ) {}

            public function generate(): Id
            {
                return new Id($this->id);
            }
        };
    }

    private function clock(): ClockInterface
    {
        return new MockClock(self::CREATED_AT);
    }
}
