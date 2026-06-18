<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Domain\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\LabelCollection;
use App\Metrics\Domain\RemoteWrite\Collection\SampleCollection;
use App\Metrics\Domain\RemoteWrite\Exception\InvalidLabel;
use App\Metrics\Domain\RemoteWrite\Exception\InvalidTimeSeries;
use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use PHPUnit\Framework\TestCase;

final class TimeSeriesTest extends TestCase
{
    public function testExposesLabelsAndSamplesInOrder(): void
    {
        $series = new TimeSeries(
            LabelCollection::of(new Label('__name__', 'netpulse_download_bits_per_second'), new Label('probe', 'home')),
            SampleCollection::of(new Sample(123.5, 1_717_000_000_000)),
        );

        $labels = $series->labels->toArray();
        $samples = $series->samples->toArray();

        self::assertCount(2, $series->labels);
        self::assertSame('__name__', $labels[0]->name);
        self::assertSame('netpulse_download_bits_per_second', $labels[0]->value);
        self::assertCount(1, $series->samples);
        self::assertSame(123.5, $samples[0]->value);
        self::assertSame(1_717_000_000_000, $samples[0]->timestampMs);
    }

    public function testRejectsSeriesWithoutNameLabel(): void
    {
        $this->expectException(InvalidTimeSeries::class);

        new TimeSeries(
            LabelCollection::of(new Label('probe', 'home')),
            SampleCollection::of(new Sample(1.0, 1_717_000_000_000)),
        );
    }

    public function testRejectsEmptyLabelName(): void
    {
        $this->expectException(InvalidLabel::class);

        new Label('', 'value');
    }
}
