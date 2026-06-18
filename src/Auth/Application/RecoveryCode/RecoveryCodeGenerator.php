<?php

declare(strict_types=1);

namespace App\Auth\Application\RecoveryCode;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use function bin2hex;
use function random_bytes;
use function substr;

final readonly class RecoveryCodeGenerator
{
    public const int CODE_COUNT = 8;

    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {}

    public function generate(): GeneratedRecoveryCodes
    {
        $hasher = $this->hasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);

        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $raw = bin2hex(random_bytes(5));
            $code = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            $plain[] = $code;
            $hashed[] = $hasher->hash($code);
        }

        return new GeneratedRecoveryCodes($plain, $hashed);
    }
}
