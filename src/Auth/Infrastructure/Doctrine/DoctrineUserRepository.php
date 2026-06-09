<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function is_numeric;

#[AsAlias(UserRepository::class)]
final class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function byEmail(Email $email): ?User
    {
        $user = $this->entityManager
            ->createQueryBuilder()
            ->select("user")
            ->from(User::class, "user")
            ->where("user.email = :email")
            ->setParameter("email", $email, "email")
            ->getQuery()
            ->getOneOrNullResult();

        if ($user === null) {
            return null;
        }

        if (!$user instanceof User) {
            throw new LogicException("Expected query to return a User instance.");
        }

        return $user;
    }

    public function count(): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select("COUNT(user.id)")
            ->from(User::class, "user")
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_numeric($count)) {
            throw new LogicException("Expected COUNT query to return a numeric value.");
        }

        return (int)$count;
    }

    public function get(UserId $id): User
    {
        $user = $this->entityManager
            ->createQueryBuilder()
            ->select("user")
            ->from(User::class, "user")
            ->where("user.id = :id")
            ->setParameter("id", $id, "user_id")
            ->getQuery()
            ->getOneOrNullResult();

        if ($user === null) {
            throw UserNotFound::withId($id);
        }

        if (!$user instanceof User) {
            throw new LogicException("Expected query to return a User instance.");
        }

        return $user;
    }
}
