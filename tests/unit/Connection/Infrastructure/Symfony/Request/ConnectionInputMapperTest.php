<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Infrastructure\Symfony\Request;

use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Infrastructure\Symfony\Request\ConnectionInputMapper;
use App\Scheduling\Domain\CronEvaluator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function str_starts_with;

final class ConnectionInputMapperTest extends TestCase
{
    private ConnectionInputMapper $mapper;

    protected function setUp(): void
    {
        $cronEvaluator = new class() implements CronEvaluator {
            public function matchesSince(string $expression, DateTimeImmutable $since, DateTimeImmutable $now): bool
            {
                return false;
            }

            public function isValid(string $expression): bool
            {
                return !str_starts_with($expression, 'bad');
            }
        };

        $this->mapper = new ConnectionInputMapper($cronEvaluator);
    }

    public function testParseLabelsSplitsPairs(): void
    {
        $this->assertSame(['site' => 'home', 'link' => 'wan1'], $this->mapper->parseLabels('site=home, link=wan1'));
    }

    public function testParseLabelsDropsMalformedPairs(): void
    {
        $this->assertSame(['a' => '1'], $this->mapper->parseLabels('a=1,nopair,=novalue'));
    }

    public function testParseLabelsOnEmptyStringIsEmpty(): void
    {
        $this->assertSame([], $this->mapper->parseLabels(''));
    }

    public function testParseListTrimsAndFiltersEmpties(): void
    {
        $this->assertSame(
            ['a.example.net', 'b.example.net'],
            $this->mapper->parseList(' a.example.net , , b.example.net '),
        );
    }

    public function testMegabitsToBits(): void
    {
        $this->assertSame(300_000_000, $this->mapper->megabitsToBits(300));
        $this->assertSame(0, $this->mapper->megabitsToBits(0));
    }

    public function testBuildEvenSchedule(): void
    {
        $schedule = $this->mapper->buildSchedule('even', [], 48, 30);

        $this->assertSame(ScheduleMode::Even, $schedule->mode());
        $this->assertSame(48, $schedule->testsPerDay());
        $this->assertSame(30, $schedule->jitterSeconds());
    }

    public function testBuildCronScheduleValidatesAndKeepsExpressions(): void
    {
        $schedule = $this->mapper->buildSchedule('cron', ['*/30 * * * *', ' 0 9 * * 1 '], 0, 0);

        $this->assertSame(ScheduleMode::Cron, $schedule->mode());
        $this->assertSame(['*/30 * * * *', '0 9 * * 1'], $schedule->cronExpressions());
    }

    public function testBuildCronScheduleRejectsInvalidExpression(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cron expression: "bad expr".');

        $this->mapper->buildSchedule('cron', ['bad expr'], 0, 0);
    }

    public function testBuildCronScheduleRejectsEmptyExpressionList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->buildSchedule('cron', ['', '   '], 0, 0);
    }

    public function testBuildEvenScheduleRejectsTooFewTests(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->buildSchedule('even', [], 0, 30);
    }

    public function testBuildEvenScheduleRejectsNegativeJitter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->buildSchedule('even', [], 24, -1);
    }

    public function testBuildThresholdsFallsBackToDefaultsForNullRatios(): void
    {
        $thresholds = $this->mapper->buildThresholds(null, null, 90.0, null, null);

        $this->assertSame(0.7, $thresholds->minDownloadRatio());
        $this->assertSame(0.7, $thresholds->minUploadRatio());
        $this->assertSame(90.0, $thresholds->maxPingMs());
        $this->assertNull($thresholds->maxJitterMs());
        $this->assertNull($thresholds->maxPacketLossRatio());
    }

    public function testBuildThresholdsUsesProvidedValues(): void
    {
        $thresholds = $this->mapper->buildThresholds(0.9, 0.5, 80.0, 40.0, 0.01);

        $this->assertSame(0.9, $thresholds->minDownloadRatio());
        $this->assertSame(0.5, $thresholds->minUploadRatio());
        $this->assertSame(0.01, $thresholds->maxPacketLossRatio());
    }

    public function testBuildAdaptivePolicyFallsBackToDefaults(): void
    {
        $policy = $this->mapper->buildAdaptivePolicy(null, null, null);

        $this->assertSame(300, $policy->adaptiveIntervalSeconds());
        $this->assertSame(3, $policy->recoveryHealthyCount());
        $this->assertSame(5, $policy->maxConsecutiveFailures());
    }

    public function testBuildAdaptivePolicyUsesProvidedValues(): void
    {
        $policy = $this->mapper->buildAdaptivePolicy(60, 1, 2);

        $this->assertSame(60, $policy->adaptiveIntervalSeconds());
        $this->assertSame(1, $policy->recoveryHealthyCount());
        $this->assertSame(2, $policy->maxConsecutiveFailures());
    }
}
