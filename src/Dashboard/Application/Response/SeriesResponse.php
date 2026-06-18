<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\SeriesBucket;
use App\Dashboard\Application\ReadModel\SeriesBucketCollection;

final readonly class SeriesResponse
{
    /**
     * @param list<array<string, int|float|null>> $buckets
     */
    private function __construct(
        public string $connectionId,
        public string $range,
        public string $metric,
        public array $buckets,
        public ?float $trendPct,
    ) {}

    public static function fromCollection(
        ConnectionId $connectionId,
        SeriesRange $range,
        SeriesMetric $metric,
        SeriesBucketCollection $series,
    ): self {
        $buckets = [];

        foreach ($series as $bucket) {
            $buckets[] = self::bucket($metric, $bucket);
        }

        return new self($connectionId->toString(), $range->value, $metric->value, $buckets, $series->trendPct());
    }

    /**
     * @return array{
     *     connectionId: string,
     *     range: string,
     *     metric: string,
     *     buckets: list<array<string, int|float|null>>,
     *     trendPct: ?float,
     * }
     */
    public function toArray(): array
    {
        return [
            'connectionId' => $this->connectionId,
            'range' => $this->range,
            'metric' => $this->metric,
            'buckets' => $this->buckets,
            'trendPct' => $this->trendPct,
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private static function bucket(SeriesMetric $metric, SeriesBucket $bucket): array
    {
        $point = ['t' => $bucket->bucketStart->getTimestamp()];

        return match ($metric) {
            SeriesMetric::Speed => $point + ['dl' => $bucket->downloadBits, 'up' => $bucket->uploadBits],
            SeriesMetric::Ping => $point + ['ping' => $bucket->pingSeconds],
            SeriesMetric::Loss => $point + ['loss' => $bucket->packetLossRatio],
        };
    }
}
