<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Oidc;

final readonly class OidcDiscoveryResult
{
    private function __construct(
        public bool $ok,
        public string $message,
    ) {}

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
