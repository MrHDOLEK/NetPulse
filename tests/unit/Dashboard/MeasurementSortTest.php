<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeasurementSortTest extends TestCase
{
    /**
     * @return iterable<string, array{MeasurementSort, array{0: string, 1: 'ASC'|'DESC'}}>
     */
    public static function orderByProvider(): iterable
    {
        yield "completed_at_desc" => [MeasurementSort::CompletedAtDesc, ["measurement.completedAt", "DESC"]];
        yield "completed_at_asc" => [MeasurementSort::CompletedAtAsc, ["measurement.completedAt", "ASC"]];
        yield "download_desc" => [MeasurementSort::DownloadDesc, ["measurement.downloadBits", "DESC"]];
        yield "download_asc" => [MeasurementSort::DownloadAsc, ["measurement.downloadBits", "ASC"]];
        yield "upload_desc" => [MeasurementSort::UploadDesc, ["measurement.uploadBits", "DESC"]];
        yield "upload_asc" => [MeasurementSort::UploadAsc, ["measurement.uploadBits", "ASC"]];
        yield "ping_desc" => [MeasurementSort::PingDesc, ["measurement.ping", "DESC"]];
        yield "ping_asc" => [MeasurementSort::PingAsc, ["measurement.ping", "ASC"]];
        yield "jitter_desc" => [MeasurementSort::JitterDesc, ["measurement.jitter", "DESC"]];
        yield "jitter_asc" => [MeasurementSort::JitterAsc, ["measurement.jitter", "ASC"]];
        yield "loss_desc" => [MeasurementSort::LossDesc, ["measurement.packetLossRatio", "DESC"]];
        yield "loss_asc" => [MeasurementSort::LossAsc, ["measurement.packetLossRatio", "ASC"]];
    }

    public function testResolvesKnownParam(): void
    {
        self::assertSame(MeasurementSort::DownloadDesc, MeasurementSort::tryFrom("download_desc"));
    }

    public function testUnknownParamResolvesToNull(): void
    {
        self::assertNull(MeasurementSort::tryFrom("nonsense"));
    }

    public function testDefaultIsCompletedAtDesc(): void
    {
        self::assertSame(MeasurementSort::CompletedAtDesc, MeasurementSort::default());
    }

    /**
     * @param array{0: string, 1: 'ASC'|'DESC'} $expected
     */
    #[DataProvider("orderByProvider")]
    public function testOrderBy(MeasurementSort $sort, array $expected): void
    {
        self::assertSame($expected, $sort->orderBy());
    }
}
