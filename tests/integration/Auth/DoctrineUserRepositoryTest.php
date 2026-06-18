<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get('test.' . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        $this->repository = $repository;
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testSavesAndFindsUserByEmailRoundTrip(): void
    {
        $user = $this->newUser();
        $this->repository->save($user);
        $this->entityManager->clear();

        $loaded = $this->repository->byEmail(new Email('owner@example.com'));

        self::assertInstanceOf(User::class, $loaded);
        self::assertTrue($loaded->id()->equals(new UserId('550e8400-e29b-41d4-a716-446655440000')));
        self::assertSame('owner@example.com', $loaded->email()->value());
        self::assertSame('hashed-secret', $loaded->password()->value());
        self::assertSame(['ROLE_ADMIN'], $loaded->roles()->toStringArray());
        self::assertEquals(new DateTimeImmutable('2026-06-05T10:00:00+00:00'), $loaded->createdAt());
    }

    public function testCountIncrementsAfterSave(): void
    {
        $before = $this->repository->count();

        $this->repository->save($this->newUser());

        self::assertSame($before + 1, $this->repository->count());
    }

    public function testByEmailReturnsNullForUnknownEmail(): void
    {
        self::assertNull($this->repository->byEmail(new Email('nobody@example.com')));
    }

    public function testGetReturnsUserById(): void
    {
        $this->repository->save($this->newUser());
        $this->entityManager->clear();

        $loaded = $this->repository->get(new UserId('550e8400-e29b-41d4-a716-446655440000'));

        self::assertSame('owner@example.com', $loaded->email()->value());
    }

    public function testGetThrowsForUnknownUser(): void
    {
        $this->expectException(UserNotFound::class);

        $this->repository->get(new UserId('11111111-1111-1111-1111-111111111111'));
    }

    private function newUser(string $uuid = '550e8400-e29b-41d4-a716-446655440000'): User
    {
        return User::register(
            new UserId($uuid),
            new Email('owner@example.com'),
            HashedPassword::fromHash('hashed-secret'),
            UserRoleCollection::fromStrings(['ROLE_ADMIN']),
            new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
        );
    }
}
