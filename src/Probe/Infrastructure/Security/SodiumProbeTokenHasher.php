<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Security;

use App\Probe\Domain\ProbeTokenHasher;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

#[AsAlias(ProbeTokenHasher::class)]
final class SodiumProbeTokenHasher implements ProbeTokenHasher
{
    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_DEFAULT);
    }

    public function verify(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }
}
