<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\Security;

use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use function sprintf;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $user = $this->users->byEmail(new Email($identifier));

        if ($user === null) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return new SecurityUser($user);
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', $user::class),
            );
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }
}
