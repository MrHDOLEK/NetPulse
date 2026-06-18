<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Domain\ValueObject\TotpSecret;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TotpSecretTypeTest extends KernelTestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXP';

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

    public function testTotpSecretRoundTripsButIsEncryptedAtRest(): void
    {
        $user = $this->newUser();
        $user->enableTotp(new TotpSecret(self::SECRET), ['hash-a', 'hash-b']);

        $this->repository->save($user);
        $this->entityManager->clear();

        $loaded = $this->repository->byEmail(new Email('owner@example.com'));
        self::assertInstanceOf(User::class, $loaded);
        self::assertTrue($loaded->hasTotp());
        self::assertNotNull($loaded->totpSecret());
        self::assertSame(self::SECRET, $loaded->totpSecret()->value());
        self::assertSame(['hash-a', 'hash-b'], $loaded->recoveryCodes());

        $stored = $this->db->fetchOne('SELECT totp_secret FROM users WHERE id = ?', [$user->id()->toString()]);

        self::assertIsString($stored);
        self::assertNotSame(self::SECRET, $stored);
        self::assertStringNotContainsString(self::SECRET, $stored);
    }

    public function testUserWithoutTotpStoresNull(): void
    {
        $user = $this->newUser();
        $this->repository->save($user);
        $this->entityManager->clear();

        $stored = $this->db->fetchOne('SELECT totp_secret FROM users WHERE id = ?', [$user->id()->toString()]);

        self::assertNull($stored);

        $loaded = $this->repository->byEmail(new Email('owner@example.com'));
        self::assertInstanceOf(User::class, $loaded);
        self::assertFalse($loaded->hasTotp());
        self::assertNull($loaded->totpSecret());
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
