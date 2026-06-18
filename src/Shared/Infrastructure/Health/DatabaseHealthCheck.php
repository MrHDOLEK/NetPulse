<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\HealthCheck;
use App\Shared\Application\Health\HealthCheckResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return HealthCheckResult::up();
        } catch (Exception $exception) {
            return HealthCheckResult::down($exception->getMessage());
        }
    }
}
