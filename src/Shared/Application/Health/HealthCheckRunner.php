<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

use Throwable;

final readonly class HealthCheckRunner
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
    ) {}

    public function run(): HealthReport
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[$check->name()] = $this->execute($check);
        }

        return new HealthReport($results);
    }

    private function execute(HealthCheck $check): HealthCheckResult
    {
        try {
            return $check->check();
        } catch (Throwable $exception) {
            return HealthCheckResult::down($exception->getMessage());
        }
    }
}
