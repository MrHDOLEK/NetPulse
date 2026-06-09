<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

final readonly class HealthReport
{
    /**
     * @param array<string, HealthCheckResult> $results
     */
    public function __construct(
        private array $results,
    ) {}

    public function isHealthy(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: string, checks: array<string, array{status: string, error?: string}>}
     */
    public function toArray(): array
    {
        $checks = [];

        foreach ($this->results as $name => $result) {
            $checks[$name] = $result->toArray();
        }

        return [
            "status" => $this->isHealthy() ? "healthy" : "unhealthy",
            "checks" => $checks,
        ];
    }
}
