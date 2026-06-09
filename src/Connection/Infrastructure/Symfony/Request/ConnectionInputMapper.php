<?php

declare(strict_types=1);

namespace App\Connection\Infrastructure\Symfony\Request;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Scheduling\Domain\CronEvaluator;
use App\Shared\Domain\ValueObject\Labels;
use InvalidArgumentException;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function sprintf;
use function trim;

final readonly class ConnectionInputMapper
{
    public function __construct(
        private CronEvaluator $cronEvaluator,
    ) {}

    /**
     * @return array<string, string>
     */
    public function parseLabels(string $raw): array
    {
        $labels = [];

        foreach ($this->parseList($raw) as $pair) {
            $parts = explode("=", $pair, 2);

            if (count($parts) === 2 && trim($parts[0]) !== "") {
                $labels[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function parseList(string $raw): array
    {
        if ($raw === "") {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(string $item): string => trim($item), explode(",", $raw)),
            static fn(string $item): bool => $item !== "",
        ));
    }

    public function megabitsToBits(int $megabits): int
    {
        return $megabits * 1_000_000;
    }

    /**
     * @param list<string> $cronExpressions
     *
     * @throws InvalidArgumentException when a cron expression is syntactically invalid, or the
     *                                  even-mode parameters are out of range
     */
    public function buildSchedule(string $mode, array $cronExpressions, int $testsPerDay, int $jitterSeconds): Schedule
    {
        if ($mode === "cron") {
            $expressions = array_values(array_filter(
                array_map(static fn(string $expression): string => trim($expression), $cronExpressions),
                static fn(string $expression): bool => $expression !== "",
            ));

            if ($expressions === []) {
                throw new InvalidArgumentException("--schedule-mode=cron requires at least one --cron expression.");
            }

            foreach ($expressions as $expression) {
                if (!$this->cronEvaluator->isValid($expression)) {
                    throw new InvalidArgumentException(sprintf('Invalid cron expression: "%s".', $expression));
                }
            }

            return Schedule::cron(...$expressions);
        }

        if ($testsPerDay < 1) {
            throw new InvalidArgumentException("--tests-per-day must be an integer >= 1.");
        }

        if ($jitterSeconds < 0) {
            throw new InvalidArgumentException("--jitter must be an integer >= 0.");
        }

        return Schedule::even($testsPerDay, $jitterSeconds);
    }

    public function buildThresholds(
        ?float $minDownloadRatio,
        ?float $minUploadRatio,
        ?float $maxPingMs,
        ?float $maxJitterMs,
        ?float $maxPacketLossRatio,
    ): Thresholds {
        $default = Thresholds::default();

        return Thresholds::of(
            $minDownloadRatio ?? $default->minDownloadRatio(),
            $minUploadRatio ?? $default->minUploadRatio(),
            $maxPingMs,
            $maxJitterMs,
            $maxPacketLossRatio,
        );
    }

    public function buildAdaptivePolicy(
        ?int $adaptiveIntervalSeconds,
        ?int $recoveryHealthyCount,
        ?int $maxConsecutiveFailures,
    ): AdaptivePolicy {
        $default = AdaptivePolicy::default();

        return AdaptivePolicy::of(
            $adaptiveIntervalSeconds ?? $default->adaptiveIntervalSeconds(),
            $recoveryHealthyCount ?? $default->recoveryHealthyCount(),
            $maxConsecutiveFailures ?? $default->maxConsecutiveFailures(),
        );
    }

    public function assemble(ConnectionRequest $request): ConnectionDraft
    {
        return new ConnectionDraft(
            $request->name,
            $request->isp,
            new ExpectedSpeed(
                $this->megabitsToBits($request->downloadMbps),
                $this->megabitsToBits($request->uploadMbps),
            ),
            ConnectionColor::from($request->color),
            Labels::fromArray($this->parseLabels($request->labels)),
            ServerPool::fromArray($this->parseList($request->serverPool)),
            $this->buildSchedule(
                $request->scheduleMode,
                $this->parseList($request->cron),
                $request->testsPerDay,
                $request->jitter,
            ),
            $this->buildThresholds(
                $request->minDownloadRatio,
                $request->minUploadRatio,
                $request->maxPingMs,
                $request->maxJitterMs,
                $request->maxPacketLossRatio,
            ),
            $this->buildAdaptivePolicy(
                $request->adaptiveIntervalSeconds,
                $request->recoveryHealthyCount,
                $request->maxConsecutiveFailures,
            ),
        );
    }
}
