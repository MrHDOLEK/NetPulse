<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\SeriesBucketCollection;

final readonly class DashboardBootstrap
{
    /**
     * @param list<array{t: int, dl: ?int, up: ?int}> $buckets
     */
    private function __construct(
        public string $connectionId,
        public string $range,
        public string $metric,
        public array $buckets,
        public ?float $trendPct,
    ) {}

    public static function fromSpeedSeries(
        ConnectionId $connectionId,
        SeriesRange $range,
        SeriesBucketCollection $series,
    ): self {
        $buckets = [];

        foreach ($series as $bucket) {
            $buckets[] = [
                "t" => $bucket->bucketStart->getTimestamp(),
                "dl" => $bucket->downloadBits,
                "up" => $bucket->uploadBits,
            ];
        }

        return new self(
            $connectionId->toString(),
            $range->value,
            SeriesMetric::Speed->value,
            $buckets,
            $series->trendPct(),
        );
    }

    /**
     * @return array{
     *     connectionId: string,
     *     range: string,
     *     metric: string,
     *     buckets: list<array{t: int, dl: ?int, up: ?int}>,
     *     trendPct: ?float,
     * }
     */
    public function toArray(): array
    {
        return [
            "connectionId" => $this->connectionId,
            "range" => $this->range,
            "metric" => $this->metric,
            "buckets" => $this->buckets,
            "trendPct" => $this->trendPct,
        ];
    }
}
