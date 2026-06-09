<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\ReadModel\Bucketing\Bucketer;
use App\Dashboard\Application\ReadModel\Bucketing\MeasurementSample;
use App\Dashboard\Application\ReadModel\Bucketing\MeasurementSampleCollection;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\SeriesBucket;
use App\Dashboard\Application\ReadModel\SeriesBucketCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeriesBucketingTest extends TestCase
{
    private const int NOW = 1_780_833_600; 

    /**
     * @return iterable<string, array{SeriesRange}>
     */
    public static function rangeProvider(): iterable
    {
        yield "day" => [SeriesRange::Day];
        yield "week" => [SeriesRange::Week];
        yield "month" => [SeriesRange::Month];
        yield "quarter" => [SeriesRange::Quarter];
    }

    #[DataProvider("rangeProvider")]
    public function testBucketCountEqualsRangeBuckets(SeriesRange $range): void
    {
        $result = $this->bucket($range, SeriesMetric::Speed, MeasurementSampleCollection::fromList([]));

        self::assertCount($range->buckets(), $result);
    }

    #[DataProvider("rangeProvider")]
    public function testBucketsAreEquallySpacedAscendingUtcCoveringTheWindow(SeriesRange $range): void
    {
        $width = $range->bucketWidthSeconds();
        $since = self::NOW - $range->windowSeconds();

        $result = $this->bucket($range, SeriesMetric::Speed, MeasurementSampleCollection::fromList([]));
        $buckets = $result->toArray();

        self::assertSame($since, $buckets[0]->bucketStart->getTimestamp());

        $previous = null;

        foreach ($buckets as $index => $bucket) {
            self::assertSame("UTC", $bucket->bucketStart->getTimezone()->getName());
            self::assertSame($since + $index * $width, $bucket->bucketStart->getTimestamp());

            if ($previous !== null) {
                self::assertSame(
                    $width,
                    $bucket->bucketStart->getTimestamp() - $previous->bucketStart->getTimestamp(),
                );
            }

            $previous = $bucket;
        }

        $last = $buckets[$range->buckets() - 1];
        self::assertLessThan(self::NOW, $last->bucketStart->getTimestamp());
        self::assertSame(self::NOW, $last->bucketStart->getTimestamp() + $width);
    }

    public function testMeasurementsInTheSameBucketAreAveraged(): void
    {
        $range = SeriesRange::Day;
        $width = $range->bucketWidthSeconds();
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 10, 100, 10),
            $this->speedSample($since + 20, 200, 20),
            $this->speedSample($since + 30, 300, 30),
        ]);

        $buckets = $this->bucket($range, SeriesMetric::Speed, $samples)->toArray();

        self::assertSame(200, $buckets[0]->downloadBits);
        self::assertSame(20, $buckets[0]->uploadBits);

        self::assertNull($buckets[1]->downloadBits);
        self::assertNull($buckets[1]->uploadBits);

        $samplesAcross = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 10, 100, 10),
            $this->speedSample($since + 5 * $width + 10, 500, 50),
        ]);
        $across = $this->bucket($range, SeriesMetric::Speed, $samplesAcross)->toArray();
        self::assertSame(100, $across[0]->downloadBits);
        self::assertSame(500, $across[5]->downloadBits);
        self::assertNull($across[3]->downloadBits);
    }

    public function testEmptyBucketsAreNullWithNoInterpolation(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->pingSample($since + 10, 0.05),
        ]);

        $buckets = $this->bucket($range, SeriesMetric::Ping, $samples)->toArray();

        self::assertEqualsWithDelta(0.05, $buckets[0]->pingSeconds, 1e-12);

        foreach (array_slice($buckets, 1) as $bucket) {
            self::assertNull($bucket->pingSeconds);
        }
    }

    public function testFailedNullRowsAreExcludedFromAverages(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $width = $range->bucketWidthSeconds();
        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 10, 100, 10),
            new MeasurementSample($since + 20, null, null, null, null),
            new MeasurementSample($since + $width + 10, null, null, null, null),
        ]);

        $buckets = $this->bucket($range, SeriesMetric::Speed, $samples)->toArray();

        self::assertSame(100, $buckets[0]->downloadBits);
        self::assertNull($buckets[1]->downloadBits);
    }

    public function testSpeedMetricFillsDownloadAndUploadOnly(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            new MeasurementSample($since + 10, 100, 10, 0.05, 0.01),
        ]);

        $bucket = $this->bucket($range, SeriesMetric::Speed, $samples)->toArray()[0];

        self::assertSame(100, $bucket->downloadBits);
        self::assertSame(10, $bucket->uploadBits);
        self::assertNull($bucket->pingSeconds);
        self::assertNull($bucket->packetLossRatio);
    }

    public function testPingMetricFillsPingOnly(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            new MeasurementSample($since + 10, 100, 10, 0.05, 0.01),
        ]);

        $bucket = $this->bucket($range, SeriesMetric::Ping, $samples)->toArray()[0];

        self::assertEqualsWithDelta(0.05, $bucket->pingSeconds, 1e-12);
        self::assertNull($bucket->downloadBits);
        self::assertNull($bucket->uploadBits);
        self::assertNull($bucket->packetLossRatio);
    }

    public function testLossMetricFillsPacketLossOnly(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            new MeasurementSample($since + 10, 100, 10, 0.05, 0.01),
        ]);

        $bucket = $this->bucket($range, SeriesMetric::Loss, $samples)->toArray()[0];

        self::assertEqualsWithDelta(0.01, $bucket->packetLossRatio, 1e-12);
        self::assertNull($bucket->downloadBits);
        self::assertNull($bucket->uploadBits);
        self::assertNull($bucket->pingSeconds);
    }

    public function testTrendPctIsCurrentVsPreviousWindowAverage(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 100, 10),
            $this->speedSample($prevSince + 200, 100, 10),
            $this->speedSample($since + 100, 100, 10),
            $this->speedSample($since + 200, 200, 20),
        ]);

        $result = $this->bucket($range, SeriesMetric::Speed, $samples);

        self::assertNotNull($result->trendPct());
        self::assertEqualsWithDelta(50.0, $result->trendPct(), 1e-9);
    }

    public function testTrendPctIsNegativeWhenCurrentBelowPrevious(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 200, 20),
            $this->speedSample($since + 100, 100, 10),
        ]);

        $result = $this->bucket($range, SeriesMetric::Speed, $samples);

        self::assertEqualsWithDelta(-50.0, $result->trendPct(), 1e-9);
    }

    public function testTrendPctNullWhenPreviousWindowEmpty(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 100, 100, 10),
        ]);

        $result = $this->bucket($range, SeriesMetric::Speed, $samples);

        self::assertNull($result->trendPct());
    }

    public function testTrendPctNullWhenCurrentWindowEmptyAndAllBucketsNull(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 100, 10),
        ]);

        $result = $this->bucket($range, SeriesMetric::Speed, $samples);

        self::assertNull($result->trendPct());

        foreach ($result as $bucket) {
            self::assertNull($bucket->downloadBits);
            self::assertNull($bucket->uploadBits);
        }
    }

    public function testTrendForSpeedIsBasedOnDownload(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 100, 999),
            $this->speedSample($since + 100, 200, 1),
        ]);

        $result = $this->bucket($range, SeriesMetric::Speed, $samples);

        self::assertEqualsWithDelta(100.0, $result->trendPct(), 1e-9);
    }

    public function testSparseCurrentWindowIsPlottedAsRawPointsAtTrueTimestamps(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 10, 100, 10),
            $this->speedSample($since + 20, 200, 20),
            $this->speedSample($since + 30, 300, 30),
            $this->speedSample($since + 40, 400, 40),
        ]);

        $buckets = $this->build($range, SeriesMetric::Speed, $samples)->toArray();

        self::assertCount(4, $buckets);
        self::assertSame(
            [$since + 10, $since + 20, $since + 30, $since + 40],
            array_map(static fn(SeriesBucket $bucket): int => $bucket->bucketStart->getTimestamp(), $buckets),
        );
        self::assertSame(
            [100, 200, 300, 400],
            array_map(static fn(SeriesBucket $bucket): ?int => $bucket->downloadBits, $buckets),
        );
        self::assertSame("UTC", $buckets[0]->bucketStart->getTimezone()->getName());
    }

    public function testDenseCurrentWindowFallsBackToBucketedAverages(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = [];

        for ($i = 0; $i < Bucketer::RAW_POINT_THRESHOLD; $i++) {
            $samples[] = $this->speedSample($since + 10 + $i, 200, 20);
        }

        $result = $this->build($range, SeriesMetric::Speed, MeasurementSampleCollection::fromList($samples));

        self::assertCount($range->buckets(), $result);

        $buckets = $result->toArray();
        self::assertSame(200, $buckets[0]->downloadBits);
        self::assertNull($buckets[1]->downloadBits);
    }

    public function testEmptyCurrentWindowKeepsTheFullBucketGrid(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 100, 10),
        ]);

        $result = $this->build($range, SeriesMetric::Speed, $samples);

        self::assertCount($range->buckets(), $result);

        foreach ($result as $bucket) {
            self::assertNull($bucket->downloadBits);
        }
    }

    public function testRawModeStillCarriesTheWindowOverWindowTrend(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();
        $prevSince = $since - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($prevSince + 100, 100, 10),
            $this->speedSample($since + 100, 100, 10),
            $this->speedSample($since + 200, 200, 20),
        ]);

        $result = $this->build($range, SeriesMetric::Speed, $samples);

        self::assertCount(2, $result);
        self::assertEqualsWithDelta(50.0, $result->trendPct(), 1e-9);
    }

    public function testRawModeEmitsANullValueForAFailedSampleAsAGap(): void
    {
        $range = SeriesRange::Day;
        $since = self::NOW - $range->windowSeconds();

        $samples = MeasurementSampleCollection::fromList([
            $this->speedSample($since + 10, 100, 10),
            new MeasurementSample($since + 20, null, null, null, null),
        ]);

        $buckets = $this->build($range, SeriesMetric::Speed, $samples)->toArray();

        self::assertCount(2, $buckets);
        self::assertSame(100, $buckets[0]->downloadBits);
        self::assertNull($buckets[1]->downloadBits);
    }

    private function build(SeriesRange $range, SeriesMetric $metric, MeasurementSampleCollection $samples): SeriesBucketCollection
    {
        $since = self::NOW - $range->windowSeconds();

        return (new Bucketer())->build(
            $since,
            $range->bucketWidthSeconds(),
            $range->buckets(),
            $metric,
            $samples,
        );
    }

    private function bucket(SeriesRange $range, SeriesMetric $metric, MeasurementSampleCollection $samples): SeriesBucketCollection
    {
        $since = self::NOW - $range->windowSeconds();

        return (new Bucketer())->bucket(
            $since,
            $range->bucketWidthSeconds(),
            $range->buckets(),
            $metric,
            $samples,
        );
    }

    private function speedSample(int $completedAtUnix, int $downloadBits, int $uploadBits): MeasurementSample
    {
        return new MeasurementSample($completedAtUnix, $downloadBits, $uploadBits, null, null);
    }

    private function pingSample(int $completedAtUnix, float $pingSeconds): MeasurementSample
    {
        return new MeasurementSample($completedAtUnix, null, null, $pingSeconds, null);
    }
}
