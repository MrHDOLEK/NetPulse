<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserNotFound;
use App\Auth\Domain\ValueObject\Email;

interface UserRepository
{
    public function save(User $user): void;

    public function byEmail(Email $email): ?User;

    public function count(): int;

    /**
     * @throws UserNotFound
     */
    public function get(UserId $id): User;
}
