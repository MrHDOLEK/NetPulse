<?php

declare(strict_types=1);

namespace App\Dashboard\Application\ReadModel\Bucketing;

use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\SeriesBucket;
use App\Dashboard\Application\ReadModel\SeriesBucketCollection;
use DateTimeImmutable;
use DateTimeZone;

use function array_sum;
use function count;
use function floor;

final readonly class Bucketer
{
    public const int RAW_POINT_THRESHOLD = 12;

    public function build(
        int $sinceUnix,
        int $bucketWidthSeconds,
        int $bucketCount,
        SeriesMetric $metric,
        MeasurementSampleCollection $samples,
    ): SeriesBucketCollection {
        $bucketed = $this->bucket($sinceUnix, $bucketWidthSeconds, $bucketCount, $metric, $samples);

        $currentSamples = $this->currentWindowSamples(
            $sinceUnix,
            $bucketWidthSeconds * $bucketCount,
            $samples,
        );
        $currentCount = count($currentSamples);

        if ($currentCount === 0 || $currentCount >= self::RAW_POINT_THRESHOLD) {
            return $bucketed;
        }

        return SeriesBucketCollection::withTrend(
            $this->rawPoints($metric, $currentSamples),
            $bucketed->trendPct(),
        );
    }

    public function bucket(
        int $sinceUnix,
        int $bucketWidthSeconds,
        int $bucketCount,
        SeriesMetric $metric,
        MeasurementSampleCollection $samples,
    ): SeriesBucketCollection {
        $windowSeconds = $bucketWidthSeconds * $bucketCount;
        $prevSinceUnix = $sinceUnix - $windowSeconds;

        /** @var array<int, list<int>> $downloadByBucket */
        $downloadByBucket = [];
        /** @var array<int, list<int>> $uploadByBucket */
        $uploadByBucket = [];
        /** @var array<int, list<float>> $pingByBucket */
        $pingByBucket = [];
        /** @var array<int, list<float>> $lossByBucket */
        $lossByBucket = [];

        /** @var list<int> $currentDownload */
        $currentDownload = [];
        /** @var list<int> $currentUpload */
        $currentUpload = [];
        /** @var list<float> $currentPing */
        $currentPing = [];
        /** @var list<float> $currentLoss */
        $currentLoss = [];

        /** @var list<int> $previousDownload */
        $previousDownload = [];
        /** @var list<int> $previousUpload */
        $previousUpload = [];
        /** @var list<float> $previousPing */
        $previousPing = [];
        /** @var list<float> $previousLoss */
        $previousLoss = [];

        foreach ($samples as $sample) {
            $timestamp = $sample->completedAtUnix;

            if ($timestamp >= $sinceUnix) {
                $index = (int)floor(($timestamp - $sinceUnix) / $bucketWidthSeconds);

                if ($index < 0 || $index >= $bucketCount) {
                    continue;
                }

                if ($sample->downloadBits !== null) {
                    $downloadByBucket[$index][] = $sample->downloadBits;
                    $currentDownload[] = $sample->downloadBits;
                }

                if ($sample->uploadBits !== null) {
                    $uploadByBucket[$index][] = $sample->uploadBits;
                    $currentUpload[] = $sample->uploadBits;
                }

                if ($sample->pingSeconds !== null) {
                    $pingByBucket[$index][] = $sample->pingSeconds;
                    $currentPing[] = $sample->pingSeconds;
                }

                if ($sample->packetLossRatio !== null) {
                    $lossByBucket[$index][] = $sample->packetLossRatio;
                    $currentLoss[] = $sample->packetLossRatio;
                }

                continue;
            }

            if ($timestamp < $prevSinceUnix) {
                continue;
            }

            if ($sample->downloadBits !== null) {
                $previousDownload[] = $sample->downloadBits;
            }

            if ($sample->uploadBits !== null) {
                $previousUpload[] = $sample->uploadBits;
            }

            if ($sample->pingSeconds !== null) {
                $previousPing[] = $sample->pingSeconds;
            }

            if ($sample->packetLossRatio !== null) {
                $previousLoss[] = $sample->packetLossRatio;
            }
        }

        $utc = new DateTimeZone("UTC");
        $buckets = [];

        for ($index = 0; $index < $bucketCount; $index++) {
            $bucketStart = (new DateTimeImmutable("@" . ($sinceUnix + $index * $bucketWidthSeconds)))
                ->setTimezone($utc);

            $buckets[] = match ($metric) {
                SeriesMetric::Speed => new SeriesBucket(
                    bucketStart: $bucketStart,
                    downloadBits: $this->averageInt($downloadByBucket[$index] ?? []),
                    uploadBits: $this->averageInt($uploadByBucket[$index] ?? []),
                ),
                SeriesMetric::Ping => new SeriesBucket(
                    bucketStart: $bucketStart,
                    pingSeconds: $this->averageFloat($pingByBucket[$index] ?? []),
                ),
                SeriesMetric::Loss => new SeriesBucket(
                    bucketStart: $bucketStart,
                    packetLossRatio: $this->averageFloat($lossByBucket[$index] ?? []),
                ),
            };
        }

        $currentAverage = match ($metric) {
            SeriesMetric::Speed => $this->averageFloat($currentDownload),
            SeriesMetric::Ping => $this->averageFloat($currentPing),
            SeriesMetric::Loss => $this->averageFloat($currentLoss),
        };

        $previousAverage = match ($metric) {
            SeriesMetric::Speed => $this->averageFloat($previousDownload),
            SeriesMetric::Ping => $this->averageFloat($previousPing),
            SeriesMetric::Loss => $this->averageFloat($previousLoss),
        };

        return SeriesBucketCollection::withTrend(
            $buckets,
            $this->trendPct($currentAverage, $previousAverage),
        );
    }

    private function trendPct(?float $currentAverage, ?float $previousAverage): ?float
    {
        if ($previousAverage === null || $previousAverage === 0.0) {
            return null;
        }

        if ($currentAverage === null) {
            return null;
        }

        return ($currentAverage - $previousAverage) / $previousAverage * 100.0;
    }

    /**
     * @return list<MeasurementSample>
     */
    private function currentWindowSamples(
        int $sinceUnix,
        int $windowSeconds,
        MeasurementSampleCollection $samples,
    ): array {
        $end = $sinceUnix + $windowSeconds;
        $current = [];

        foreach ($samples as $sample) {
            if ($sample->completedAtUnix >= $sinceUnix && $sample->completedAtUnix < $end) {
                $current[] = $sample;
            }
        }

        return $current;
    }

    /**
     * @param list<MeasurementSample> $samples
     *
     * @return list<SeriesBucket>
     */
    private function rawPoints(SeriesMetric $metric, array $samples): array
    {
        $utc = new DateTimeZone("UTC");
        $points = [];

        foreach ($samples as $sample) {
            $at = (new DateTimeImmutable("@" . $sample->completedAtUnix))->setTimezone($utc);

            $points[] = match ($metric) {
                SeriesMetric::Speed => new SeriesBucket(
                    bucketStart: $at,
                    downloadBits: $sample->downloadBits,
                    uploadBits: $sample->uploadBits,
                ),
                SeriesMetric::Ping => new SeriesBucket(bucketStart: $at, pingSeconds: $sample->pingSeconds),
                SeriesMetric::Loss => new SeriesBucket(bucketStart: $at, packetLossRatio: $sample->packetLossRatio),
            };
        }

        return $points;
    }

    /**
     * @param list<int> $values
     */
    private function averageInt(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        return (int)round(array_sum($values) / count($values));
    }

    /**
     * @param list<int|float> $values
     */
    private function averageFloat(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }
}
