<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Health;

use App\Shared\Application\Health\HealthCheck;
use App\Shared\Application\Health\HealthCheckResult;
use App\Shared\Application\Health\HealthCheckRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final readonly class FixedHealthCheck implements HealthCheck
{
    public function __construct(
        private string $name,
        private HealthCheckResult $result,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        return $this->result;
    }
}

final readonly class ThrowingHealthCheck implements HealthCheck
{
    public function __construct(
        private string $name,
        private string $message,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        throw new RuntimeException($this->message);
    }
}

final class HealthCheckRunnerTest extends TestCase
{
    /**
     * @return iterable<string, array{list<HealthCheck>, bool}>
     */
    public static function provideAggregations(): iterable
    {
        yield "all up" => [
            [
                new FixedHealthCheck("a", HealthCheckResult::up()),
                new FixedHealthCheck("b", HealthCheckResult::up()),
            ],
            true,
        ];

        yield "first down" => [
            [
                new FixedHealthCheck("a", HealthCheckResult::down("nope")),
                new FixedHealthCheck("b", HealthCheckResult::up()),
            ],
            false,
        ];

        yield "last down" => [
            [
                new FixedHealthCheck("a", HealthCheckResult::up()),
                new FixedHealthCheck("b", HealthCheckResult::down("nope")),
            ],
            false,
        ];
    }

    public function testReportIsHealthyWhenEveryCheckIsUp(): void
    {
        $runner = new HealthCheckRunner([
            new FixedHealthCheck("database", HealthCheckResult::up()),
            new FixedHealthCheck("cache", HealthCheckResult::up()),
        ]);

        $report = $runner->run();

        self::assertTrue($report->isHealthy());
        self::assertSame([
            "status" => "healthy",
            "checks" => [
                "database" => ["status" => "up"],
                "cache" => ["status" => "up"],
            ],
        ], $report->toArray());
    }

    public function testReportIsUnhealthyWhenAnyCheckIsDown(): void
    {
        $runner = new HealthCheckRunner([
            new FixedHealthCheck("database", HealthCheckResult::up()),
            new FixedHealthCheck("cache", HealthCheckResult::down("connection refused")),
        ]);

        $report = $runner->run();

        self::assertFalse($report->isHealthy());
        self::assertSame([
            "status" => "unhealthy",
            "checks" => [
                "database" => ["status" => "up"],
                "cache" => ["status" => "down", "error" => "connection refused"],
            ],
        ], $report->toArray());
    }

    public function testThrowingCheckBecomesDownWithExceptionMessage(): void
    {
        $runner = new HealthCheckRunner([
            new ThrowingHealthCheck("database", "boom"),
        ]);

        $report = $runner->run();

        self::assertFalse($report->isHealthy());
        self::assertSame([
            "status" => "unhealthy",
            "checks" => [
                "database" => ["status" => "down", "error" => "boom"],
            ],
        ], $report->toArray());
    }

    public function testEmptyReportIsHealthy(): void
    {
        $report = (new HealthCheckRunner([]))->run();

        self::assertTrue($report->isHealthy());
        self::assertSame([
            "status" => "healthy",
            "checks" => [],
        ], $report->toArray());
    }

    /**
     * @param list<HealthCheck> $checks
     */
    #[DataProvider("provideAggregations")]
    public function testAggregatesHealthAcrossChecks(array $checks, bool $expectedHealthy): void
    {
        $report = (new HealthCheckRunner($checks))->run();

        self::assertSame($expectedHealthy, $report->isHealthy());
    }
}
