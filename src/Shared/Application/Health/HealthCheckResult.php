<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

final readonly class HealthCheckResult
{
    private function __construct(
        private bool $healthy,
        private ?string $error,
    ) {}

    public static function up(): self
    {
        return new self(true, null);
    }

    public static function down(string $error): self
    {
        return new self(false, $error);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @return array{status: string, error?: string}
     */
    public function toArray(): array
    {
        if ($this->healthy) {
            return ["status" => "up"];
        }

        return [
            "status" => "down",
            "error" => $this->error ?? "",
        ];
    }
}
