<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\Security;

use App\Auth\Domain\Entity\User\User;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_unique;
use function array_values;
use function in_array;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    public function __construct(
        private User $user,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    public function getUserIdentifier(): string
    {
        return $this->user->email()->value();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->user->roles()->toStringArray();

        if (!in_array("ROLE_USER", $roles, true)) {
            $roles[] = "ROLE_USER";
        }

        return array_values(array_unique($roles));
    }

    public function getPassword(): string
    {
        return $this->user->password()->value();
    }

    public function eraseCredentials(): void
    {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->user->hasTotp();
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->user->email()->value();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        $secret = $this->user->totpSecret();

        if ($secret === null) {
            return null;
        }

        return new TotpConfiguration($secret->value(), TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
