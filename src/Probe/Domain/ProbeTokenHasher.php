<?php

declare(strict_types=1);

namespace App\Probe\Domain;

interface ProbeTokenHasher
{
    public function hash(string $plaintext): string;

    public function verify(string $plaintext, string $hash): bool;
}
