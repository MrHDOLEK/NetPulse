<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

interface HealthCheck
{
    public function name(): string;

    public function check(): HealthCheckResult;
}
