<?php

declare(strict_types=1);

namespace App\Auth\Domain\Entity\User;

use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use App\Auth\Domain\ValueObject\TotpSecret;
use DateTimeImmutable;

use function in_array;

class User
{
    private ?TotpSecret $totpSecret = null;

    /** @var list<string> */
    private array $recoveryCodes = [];

    public function __construct(
        private readonly UserId $id,
        private readonly Email $email,
        private HashedPassword $password,
        private UserRoleCollection $roles,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        UserRoleCollection $roles,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $password, $roles, $createdAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function roles(): UserRoleCollection
    {
        return $this->roles;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changePassword(HashedPassword $new): void
    {
        $this->password = $new;
    }

    public function hasTotp(): bool
    {
        return $this->totpSecret !== null;
    }

    public function totpSecret(): ?TotpSecret
    {
        return $this->totpSecret;
    }

    /**
     * @param list<string> $hashedRecoveryCodes
     */
    public function enableTotp(TotpSecret $secret, array $hashedRecoveryCodes): void
    {
        $this->totpSecret = $secret;
        $this->recoveryCodes = $hashedRecoveryCodes;
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->recoveryCodes = [];
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(): array
    {
        return $this->recoveryCodes;
    }

    public function consumeRecoveryCode(string $hashedCode): void
    {
        if (!in_array($hashedCode, $this->recoveryCodes, true)) {
            return;
        }

        $remaining = [];

        foreach ($this->recoveryCodes as $code) {
            if ($code !== $hashedCode) {
                $remaining[] = $code;
            }
        }

        $this->recoveryCodes = $remaining;
    }

    /**
     * @param list<string> $hashedRecoveryCodes
     */
    public function replaceRecoveryCodes(array $hashedRecoveryCodes): void
    {
        $this->recoveryCodes = $hashedRecoveryCodes;
    }
}
