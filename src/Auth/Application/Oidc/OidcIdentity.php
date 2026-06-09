<?php

declare(strict_types=1);

namespace App\Auth\Application\Oidc;

final readonly class OidcIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
