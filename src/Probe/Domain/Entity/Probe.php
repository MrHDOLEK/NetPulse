<?php

declare(strict_types=1);

namespace App\Probe\Domain\Entity;

use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;

class Probe
{
    public function __construct(
        private readonly ProbeId $id,
        private string $name,
        private Labels $labels,
        private string $tokenHash,
        private bool $enabled,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $lastPollAt = null,
    ) {}

    public function id(): ProbeId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function labels(): Labels
    {
        return $this->labels;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastPollAt(): ?DateTimeImmutable
    {
        return $this->lastPollAt;
    }

    public function verifyToken(string $plaintext, ProbeTokenHasher $hasher): bool
    {
        return $hasher->verify($plaintext, $this->tokenHash);
    }

    public function rotateToken(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
