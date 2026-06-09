<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> keyed by lowercase email */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->email()->value()] = $user;
    }

    public function byEmail(Email $email): ?User
    {
        return $this->users[$email->value()] ?? null;
    }

    public function count(): int
    {
        return count($this->users);
    }

    public function get(UserId $id): User
    {
        foreach ($this->users as $user) {
            if ($user->id()->equals($id)) {
                return $user;
            }
        }

        throw UserNotFound::withId($id);
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        return array_values($this->users);
    }
}
