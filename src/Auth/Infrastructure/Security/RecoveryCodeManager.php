<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\UserRepository;
use App\Auth\Infrastructure\Symfony\Security\SecurityUser;
use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final readonly class RecoveryCodeManager implements BackupCodeManagerInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
        private UserRepository $users,
    ) {}

    public function isBackupCode(object $user, #[SensitiveParameter] string $code): bool
    {
        $domainUser = $this->domainUser($user);

        if ($domainUser === null) {
            return false;
        }

        return $this->matchingHash($domainUser, $code) !== null;
    }

    public function invalidateBackupCode(object $user, #[SensitiveParameter] string $code): void
    {
        $domainUser = $this->domainUser($user);

        if ($domainUser === null) {
            return;
        }

        $hash = $this->matchingHash($domainUser, $code);

        if ($hash === null) {
            return;
        }

        $domainUser->consumeRecoveryCode($hash);
        $this->users->save($domainUser);
    }

    private function matchingHash(User $user, #[SensitiveParameter] string $code): ?string
    {
        $hasher = $this->hasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);

        foreach ($user->recoveryCodes() as $hash) {
            if ($hasher->verify($hash, $code)) {
                return $hash;
            }
        }

        return null;
    }

    private function domainUser(object $user): ?User
    {
        if ($user instanceof SecurityUser) {
            return $user->getUser();
        }

        if ($user instanceof User) {
            return $user;
        }

        return null;
    }
}
