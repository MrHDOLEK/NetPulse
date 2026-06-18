<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRole;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Infrastructure\Doctrine\Type\UserRoleCollectionType;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRoleCollectionTypeTest extends KernelTestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $entityManager;
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get('test.' . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        $this->repository = $repository;
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->db = $container->get('doctrine.dbal.default_connection');
    }

    public function testRolesRoundTripAsAPlainJsonArray(): void
    {
        $user = $this->userWith(UserRole::Admin, UserRole::User);
        $this->repository->save($user);
        $this->entityManager->clear();

        $loaded = $this->repository->byEmail(new Email('owner@example.com'));
        self::assertInstanceOf(User::class, $loaded);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $loaded->roles()->toStringArray());

        $stored = $this->db->fetchOne('SELECT roles FROM users WHERE id = ?', [$user->id()->toString()]);
        self::assertIsString($stored);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], json_decode($stored, true));
    }

    public function testUnknownPersistedRoleFailsClosedOnHydration(): void
    {
        $platform = $this->db->getDatabasePlatform();
        $type = Type::getType(UserRoleCollectionType::NAME);

        $this->expectException(ValueNotConvertible::class);

        $type->convertToPHPValue('["ROLE_ADMIN","ROLE_BOGUS"]', $platform);
    }

    private function userWith(UserRole ...$roles): User
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
