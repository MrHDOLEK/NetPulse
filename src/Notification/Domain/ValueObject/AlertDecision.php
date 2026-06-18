<?php

declare(strict_types=1);

namespace App\Notification\Domain\ValueObject;

use App\Notification\Domain\Enum\NotificationKind;

final readonly class AlertDecision
{
    private function __construct(
        private ?NotificationKind $kind,
        public string $reason,
    ) {}

    public static function alert(string $reason): self
    {
        return new self(NotificationKind::Alert, $reason);
    }

    public static function recovery(string $reason): self
    {
        return new self(NotificationKind::Recovery, $reason);
    }

    public static function none(): self
    {
        return new self(null, 'no edge crossed');
    }

    public function shouldNotify(): bool
    {
        return $this->kind !== null;
    }

    public function kind(): ?NotificationKind
    {
        return $this->kind;
    }
}
