<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Health;

use App\Shared\Application\Health\HealthCheckResult;
use PHPUnit\Framework\TestCase;

final class HealthCheckResultTest extends TestCase
{
    public function testUpHasNoError(): void
    {
        $result = HealthCheckResult::up();

        self::assertTrue($result->isHealthy());
        self::assertNull($result->error());
        self::assertSame(['status' => 'up'], $result->toArray());
    }

    public function testDownCarriesError(): void
    {
        $result = HealthCheckResult::down('connection refused');

        self::assertFalse($result->isHealthy());
        self::assertSame('connection refused', $result->error());
        self::assertSame(['status' => 'down', 'error' => 'connection refused'], $result->toArray());
    }
}
