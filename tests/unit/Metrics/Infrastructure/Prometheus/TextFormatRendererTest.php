<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\Prometheus;

use App\Metrics\Application\ReadModel\DegradedRow;
use App\Metrics\Application\ReadModel\DegradedRowCollection;
use App\Metrics\Application\ReadModel\ExpectedRow;
use App\Metrics\Application\ReadModel\ExpectedRowCollection;
use App\Metrics\Application\ReadModel\LatestMeasurementRow;
use App\Metrics\Application\ReadModel\LatestMeasurementRowCollection;
use App\Metrics\Application\ReadModel\NotificationSendRow;
use App\Metrics\Application\ReadModel\NotificationSendRowCollection;
use App\Metrics\Application\ReadModel\RunCountRow;
use App\Metrics\Application\ReadModel\RunCountRowCollection;
use App\Metrics\Application\ReadModel\UnhealthyCountRow;
use App\Metrics\Application\ReadModel\UnhealthyCountRowCollection;
use App\Metrics\Infrastructure\Prometheus\TextFormatRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Symfony\Component\Clock\MockClock;

final class TextFormatRendererTest extends TestCase
{
    private const int NOW = 1717545600; 

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideExpectedLines(): iterable
    {
        yield "build_info type" => ["# TYPE netpulse_build_info gauge"];
        yield "build_info sample" => ["netpulse_build_info{version=\"0.1.0\"} 1"];
        yield "connection_info type" => ["# TYPE netpulse_connection_info gauge"];
        yield "connection_info sample" => ["netpulse_connection_info{probe=\"home\",connection=\"wan1\",site=\"home-lab\",isp=\"Acme ISP\"} 1"];

        yield "up type" => ["# TYPE netpulse_up gauge"];
        yield "up fresh" => ["netpulse_up{probe=\"home\",connection=\"wan1\"} 1"];
        yield "up stale" => ["netpulse_up{probe=\"home\",connection=\"wan2\"} 0"];

        yield "download gauge" => ["netpulse_download_bits_per_second{probe=\"home\",connection=\"wan1\",server_name=\"Acme Speedtest\",server_id=\"12345\",isp=\"Acme ISP\"} 950000000"];
        yield "ping gauge float" => ["netpulse_ping_seconds{probe=\"home\",connection=\"wan1\",server_name=\"Acme Speedtest\",server_id=\"12345\",isp=\"Acme ISP\"} 0.012"];
        yield "packet loss zero" => ["netpulse_packet_loss_ratio{probe=\"home\",connection=\"wan1\",server_name=\"Acme Speedtest\",server_id=\"12345\",isp=\"Acme ISP\"} 0"];
        yield "last result timestamp" => ["netpulse_last_result_timestamp_seconds{probe=\"home\",connection=\"wan1\"} " . (self::NOW - 100)];

        yield "expected download" => ["netpulse_connection_expected_download_bits_per_second{probe=\"home\",connection=\"wan1\"} 1000000000"];

        yield "runs_total type" => ["# TYPE netpulse_speedtest_runs_total counter"];
        yield "runs_total completed" => ["netpulse_speedtest_runs_total{probe=\"home\",connection=\"wan1\",status=\"completed\"} 2"];
        yield "runs_total failed" => ["netpulse_speedtest_runs_total{probe=\"home\",connection=\"wan1\",status=\"failed\"} 1"];
        yield "failures_total type" => ["# TYPE netpulse_speedtest_failures_total counter"];
        yield "failures_total wan1" => ["netpulse_speedtest_failures_total{probe=\"home\",connection=\"wan1\"} 1"];
        yield "failures_total wan2 zero" => ["netpulse_speedtest_failures_total{probe=\"home\",connection=\"wan2\"} 0"];
        yield "remote_write_failures type" => ["# TYPE netpulse_remote_write_failures_total counter"];
        yield "remote_write_failures value" => ["netpulse_remote_write_failures_total 4"];

        yield "connection_healthy type" => ["# TYPE netpulse_connection_healthy gauge"];
        yield "connection_healthy true" => ["netpulse_connection_healthy{probe=\"home\",connection=\"wan1\",site=\"home-lab\",server_name=\"Acme Speedtest\",server_id=\"12345\",isp=\"Acme ISP\"} 1"];
        yield "connection_healthy false" => ["netpulse_connection_healthy{probe=\"home\",connection=\"wan2\",site=\"home-lab\",server_name=\"Other Server\",server_id=\"67890\",isp=\"Acme ISP\"} 0"];

        yield "connection_degraded type" => ["# TYPE netpulse_connection_degraded gauge"];
        yield "connection_degraded true" => ["netpulse_connection_degraded{probe=\"home\",connection=\"wan2\"} 1"];
        yield "connection_degraded false" => ["netpulse_connection_degraded{probe=\"home\",connection=\"wan1\"} 0"];

        yield "unhealthy_total type" => ["# TYPE netpulse_speedtest_unhealthy_total counter"];
        yield "unhealthy_total wan2" => ["netpulse_speedtest_unhealthy_total{probe=\"home\",connection=\"wan2\"} 3"];
        yield "unhealthy_total wan1 zero" => ["netpulse_speedtest_unhealthy_total{probe=\"home\",connection=\"wan1\"} 0"];

        yield "notifications_sent type" => ["# TYPE netpulse_notifications_sent_total counter"];
        yield "notifications_sent alert webhook" => ["netpulse_notifications_sent_total{kind=\"alert\",channel=\"webhook\",status=\"sent\"} 1"];
        yield "notifications_sent recovery webhook" => ["netpulse_notifications_sent_total{kind=\"recovery\",channel=\"webhook\",status=\"sent\"} 1"];
        yield "notifications_sent digest webhook" => ["netpulse_notifications_sent_total{kind=\"digest\",channel=\"webhook\",status=\"sent\"} 1"];
        yield "notifications_sent alert email failed" => ["netpulse_notifications_sent_total{kind=\"alert\",channel=\"email\",status=\"failed\"} 2"];
        yield "notifications_sent digest webhook skipped" => ["netpulse_notifications_sent_total{kind=\"digest\",channel=\"webhook\",status=\"skipped\"} 1"];
    }

    #[DataProvider("provideExpectedLines")]
    public function testRendersExpectedFamiliesAndSamples(string $expectedLine): void
    {
        $output = $this->renderSample();

        self::assertStringContainsString($expectedLine, $output);
    }

    public function testSkipsNullGaugeValuesFromFailedMeasurements(): void
    {
        $failed = $this->row(
            connectionName: "wan3",
            serverId: "0",
            serverName: "",
            completedAtUnix: self::NOW - 30,
            downloadBits: null,
            uploadBits: null,
            pingSeconds: null,
            jitterSeconds: null,
            packetLossRatio: null,
            downloadLatencyIqmSeconds: null,
            uploadLatencyIqmSeconds: null,
            dataUsedBytes: null,
        );

        $renderer = new TextFormatRenderer(
            new MockClock(new DateTimeImmutable("@" . self::NOW)),
            new CollectorRegistry(new InMemory(), false),
            "0.1.0",
        );

        $output = $renderer->render(
            LatestMeasurementRowCollection::of($failed),
            RunCountRowCollection::of(),
            ExpectedRowCollection::of(),
            UnhealthyCountRowCollection::of(),
            DegradedRowCollection::of(),
            0,
            NotificationSendRowCollection::of(),
            3600,
        );

        self::assertStringContainsString("netpulse_up{probe=\"home\",connection=\"wan3\"} 1", $output);

        self::assertStringNotContainsString("netpulse_download_bits_per_second{probe=\"home\",connection=\"wan3\"", $output);
        self::assertStringNotContainsString("netpulse_ping_seconds{probe=\"home\",connection=\"wan3\"", $output);

        self::assertStringNotContainsString("netpulse_connection_healthy{probe=\"home\",connection=\"wan3\"", $output);
    }

    private function renderSample(): string
    {
        $fresh = $this->row(
            connectionName: "wan1",
            serverId: "12345",
            serverName: "Acme Speedtest",
            completedAtUnix: self::NOW - 100,
            downloadBits: 950000000,
            uploadBits: 480000000,
            pingSeconds: 0.012,
            jitterSeconds: 0.0021,
            packetLossRatio: 0.0,
            downloadLatencyIqmSeconds: 0.015,
            uploadLatencyIqmSeconds: 0.018,
            dataUsedBytes: 123456789,
            healthy: true,
        );
        $stale = $this->row(
            connectionName: "wan2",
            serverId: "67890",
            serverName: "Other Server",
            completedAtUnix: self::NOW - 100000,
            downloadBits: 500000000,
            uploadBits: 200000000,
            pingSeconds: 0.030,
            jitterSeconds: 0.005,
            packetLossRatio: 0.01,
            downloadLatencyIqmSeconds: 0.040,
            uploadLatencyIqmSeconds: 0.045,
            dataUsedBytes: 50000000,
            healthy: false,
        );

        $runCounts = RunCountRowCollection::of(
            new RunCountRow("p1", "home", "c1", "wan1", "completed", 2),
            new RunCountRow("p1", "home", "c1", "wan1", "failed", 1),
            new RunCountRow("p1", "home", "c2", "wan2", "completed", 1),
        );
        $expected = ExpectedRowCollection::of(
            new ExpectedRow("c1", "wan1", "home", 1000000000, 500000000),
            new ExpectedRow("c2", "wan2", "home", 600000000, 300000000),
        );
        $unhealthyCounts = UnhealthyCountRowCollection::of(
            new UnhealthyCountRow("home", "wan1", 0),
            new UnhealthyCountRow("home", "wan2", 3),
        );
        $degraded = DegradedRowCollection::of(
            new DegradedRow("home", "wan1", false),
            new DegradedRow("home", "wan2", true),
        );
        $notificationSends = NotificationSendRowCollection::of(
            new NotificationSendRow("alert", "webhook", "sent", 1),
            new NotificationSendRow("alert", "email", "failed", 2),
            new NotificationSendRow("recovery", "webhook", "sent", 1),
            new NotificationSendRow("digest", "webhook", "sent", 1),
            new NotificationSendRow("digest", "webhook", "skipped", 1),
        );

        $renderer = new TextFormatRenderer(
            new MockClock(new DateTimeImmutable("@" . self::NOW)),
            new CollectorRegistry(new InMemory(), false),
            "0.1.0",
        );

        return $renderer->render(
            LatestMeasurementRowCollection::of($fresh, $stale),
            $runCounts,
            $expected,
            $unhealthyCounts,
            $degraded,
            4,
            $notificationSends,
            3600,
        );
    }

    private function row(
        string $connectionName,
        string $serverId,
        string $serverName,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingSeconds,
        ?float $jitterSeconds,
        ?float $packetLossRatio,
        ?float $downloadLatencyIqmSeconds,
        ?float $uploadLatencyIqmSeconds,
        ?int $dataUsedBytes,
        ?bool $healthy = null,
    ): LatestMeasurementRow {
        return new LatestMeasurementRow(
            probeId: "p1",
            probeName: "home",
            connectionId: "c-" . $connectionName,
            connectionName: $connectionName,
            isp: "Acme ISP",
            serverId: $serverId,
            serverName: $serverName,
            serverLocation: "Warsaw",
            site: "home-lab",
            status: "completed",
            completedAtUnix: $completedAtUnix,
            downloadBits: $downloadBits,
            uploadBits: $uploadBits,
            pingSeconds: $pingSeconds,
            jitterSeconds: $jitterSeconds,
            packetLossRatio: $packetLossRatio,
            downloadLatencyIqmSeconds: $downloadLatencyIqmSeconds,
            uploadLatencyIqmSeconds: $uploadLatencyIqmSeconds,
            dataUsedBytes: $dataUsedBytes,
            healthy: $healthy,
        );
    }
}
