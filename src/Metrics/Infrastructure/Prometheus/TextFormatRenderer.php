<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Prometheus;

use App\Metrics\Application\ReadModel\DegradedRowCollection;
use App\Metrics\Application\ReadModel\ExpectedRow;
use App\Metrics\Application\ReadModel\ExpectedRowCollection;
use App\Metrics\Application\ReadModel\LatestMeasurementRow;
use App\Metrics\Application\ReadModel\LatestMeasurementRowCollection;
use App\Metrics\Application\ReadModel\NotificationSendRowCollection;
use App\Metrics\Application\ReadModel\RunCountRowCollection;
use App\Metrics\Application\ReadModel\UnhealthyCountRowCollection;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TextFormatRenderer
{
    private const string NAMESPACE = 'netpulse';

    public function __construct(
        private ClockInterface $clock,
        private CollectorRegistry $registry,
        #[Autowire('%netpulse.build.version%')]
        private string $buildVersion,
    ) {}

    public function render(
        LatestMeasurementRowCollection $latest,
        RunCountRowCollection $runCounts,
        ExpectedRowCollection $expected,
        UnhealthyCountRowCollection $unhealthyCounts,
        DegradedRowCollection $degraded,
        int $remoteWriteFailures,
        NotificationSendRowCollection $notificationSends,
        int $freshnessWindowSeconds,
    ): string {
        $this->registry->wipeStorage();

        $this->populateBuildInfo();
        $this->populateConnectionInfo($latest);
        $this->populateUp($latest, $freshnessWindowSeconds);
        $this->populateLastResultTimestamp($latest);
        $this->populateServerGauges($latest);
        $this->populateConnectionHealthy($latest);
        $this->populateConnectionDegraded($degraded);
        $this->populateExpected($expected);
        $this->populateRunsTotal($runCounts);
        $this->populateFailuresTotal($runCounts);
        $this->populateUnhealthyTotal($unhealthyCounts);
        $this->populateRemoteWriteFailures($remoteWriteFailures);
        $this->populateNotificationsSent($notificationSends);

        return new RenderTextFormat()->render($this->registry->getMetricFamilySamples());
    }

    private function populateBuildInfo(): void
    {
        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'build_info',
            'Build information of the NetPulse exporter.',
            ['version'],
        )->set(1, [$this->buildVersion]);
    }

    private function populateConnectionInfo(LatestMeasurementRowCollection $latest): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'connection_info',
            'Static information about a monitored connection.',
            ['probe', 'connection', 'site', 'isp'],
        );

        foreach ($latest as $row) {
            $gauge->set(1, [$row->probeName, $row->connectionName, $row->site, $row->isp]);
        }
    }

    private function populateUp(LatestMeasurementRowCollection $latest, int $freshnessWindowSeconds): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'up',
            'Whether the connection has a fresh successful measurement within the freshness window.',
            ['probe', 'connection'],
        );

        $threshold = $this->clock->now()->getTimestamp() - $freshnessWindowSeconds;

        foreach ($latest as $row) {
            $up = $row->completedAtUnix >= $threshold ? 1 : 0;
            $gauge->set($up, [$row->probeName, $row->connectionName]);
        }
    }

    private function populateLastResultTimestamp(LatestMeasurementRowCollection $latest): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'last_result_timestamp_seconds',
            'Unix timestamp of the last successful measurement.',
            ['probe', 'connection'],
        );

        foreach ($latest as $row) {
            $gauge->set($row->completedAtUnix, [$row->probeName, $row->connectionName]);
        }
    }

    private function populateServerGauges(LatestMeasurementRowCollection $latest): void
    {
        $gauges = [
            [
                'download_bits_per_second',
                'Last measured download throughput in bits per second.',
                static fn(LatestMeasurementRow $r): ?float => $r->downloadBits === null
                    ? null
                    : (float) $r->downloadBits,
            ],
            [
                'upload_bits_per_second',
                'Last measured upload throughput in bits per second.',
                static fn(LatestMeasurementRow $r): ?float => $r->uploadBits === null ? null : (float) $r->uploadBits,
            ],
            [
                'ping_seconds',
                'Last measured idle latency in seconds.',
                static fn(LatestMeasurementRow $r): ?float => $r->pingSeconds,
            ],
            [
                'jitter_seconds',
                'Last measured idle jitter in seconds.',
                static fn(LatestMeasurementRow $r): ?float => $r->jitterSeconds,
            ],
            [
                'packet_loss_ratio',
                'Last measured packet loss as a ratio between 0 and 1.',
                static fn(LatestMeasurementRow $r): ?float => $r->packetLossRatio,
            ],
            [
                'download_latency_iqm_seconds',
                'Last measured loaded download latency (IQM) in seconds.',
                static fn(LatestMeasurementRow $r): ?float => $r->downloadLatencyIqmSeconds,
            ],
            [
                'upload_latency_iqm_seconds',
                'Last measured loaded upload latency (IQM) in seconds.',
                static fn(LatestMeasurementRow $r): ?float => $r->uploadLatencyIqmSeconds,
            ],
            [
                'data_used_bytes',
                'Bytes transferred during the last measurement.',
                static fn(LatestMeasurementRow $r): ?float => $r->dataUsedBytes === null
                    ? null
                    : (float) $r->dataUsedBytes,
            ],
        ];

        foreach ($gauges as [$name, $help, $value]) {
            $gauge = $this->registry->getOrRegisterGauge(self::NAMESPACE, $name, $help, [
                'probe',
                'connection',
                'server_name',
                'server_id',
                'isp',
            ]);

            foreach ($latest as $row) {
                $resolved = $value($row);

                if ($resolved === null) {
                    continue;
                }

                $gauge->set($resolved, [
                    $row->probeName,
                    $row->connectionName,
                    $row->serverName,
                    $row->serverId,
                    $row->isp,
                ]);
            }
        }
    }

    private function populateConnectionHealthy(LatestMeasurementRowCollection $latest): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'connection_healthy',
            "Whether the latest measurement passed the connection's health thresholds (1 healthy, 0 unhealthy).",
            ['probe', 'connection', 'site', 'server_name', 'server_id', 'isp'],
        );

        foreach ($latest as $row) {
            if ($row->healthy === null) {
                continue;
            }

            $gauge->set($row->healthy ? 1 : 0, [
                $row->probeName,
                $row->connectionName,
                $row->site,
                $row->serverName,
                $row->serverId,
                $row->isp,
            ]);
        }
    }

    private function populateConnectionDegraded(DegradedRowCollection $degraded): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'connection_degraded',
            'Whether the connection is currently degraded per the adaptive scheduling predicate (1 degraded, 0 healthy).',
            ['probe', 'connection'],
        );

        foreach ($degraded as $row) {
            $gauge->set($row->degraded ? 1 : 0, [$row->probeName, $row->connectionName]);
        }
    }

    private function populateExpected(ExpectedRowCollection $expected): void
    {
        $gauges = [
            [
                'connection_expected_download_bits_per_second',
                'Configured expected download speed in bits per second.',
                static fn(ExpectedRow $r): int => $r->expectedDownloadBits,
            ],
            [
                'connection_expected_upload_bits_per_second',
                'Configured expected upload speed in bits per second.',
                static fn(ExpectedRow $r): int => $r->expectedUploadBits,
            ],
        ];

        foreach ($gauges as [$name, $help, $value]) {
            $gauge = $this->registry->getOrRegisterGauge(self::NAMESPACE, $name, $help, ['probe', 'connection']);

            foreach ($expected as $row) {
                $gauge->set($value($row), [$row->probeName, $row->connectionName]);
            }
        }
    }

    private function populateRunsTotal(RunCountRowCollection $runCounts): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'speedtest_runs_total',
            'Total number of speedtest runs by status.',
            ['probe', 'connection', 'status'],
        );

        foreach ($runCounts as $row) {
            $counter->incBy($row->count, [$row->probeName, $row->connectionName, $row->status]);
        }
    }

    private function populateFailuresTotal(RunCountRowCollection $runCounts): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'speedtest_failures_total',
            'Total number of failed speedtest runs.',
            ['probe', 'connection'],
        );

        /** @var array<string, array{probe: string, connection: string, failures: int}> $perConnection */
        $perConnection = [];

        foreach ($runCounts as $row) {
            $key = $row->probeName . "\x00" . $row->connectionName;

            if (!isset($perConnection[$key])) {
                $perConnection[$key] = [
                    'probe' => $row->probeName,
                    'connection' => $row->connectionName,
                    'failures' => 0,
                ];
            }

            if ($row->status === 'failed') {
                $perConnection[$key]['failures'] += $row->count;
            }
        }

        foreach ($perConnection as $entry) {
            $counter->incBy($entry['failures'], [$entry['probe'], $entry['connection']]);
        }
    }

    private function populateUnhealthyTotal(UnhealthyCountRowCollection $unhealthyCounts): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'speedtest_unhealthy_total',
            'Total number of completed speedtest runs whose result was unhealthy.',
            ['probe', 'connection'],
        );

        foreach ($unhealthyCounts as $row) {
            $counter->incBy($row->count, [$row->probeName, $row->connectionName]);
        }
    }

    private function populateRemoteWriteFailures(int $remoteWriteFailures): void
    {
        $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'remote_write_failures_total',
            'Total number of remote write push failures.',
            [],
        )->incBy($remoteWriteFailures, []);
    }

    private function populateNotificationsSent(NotificationSendRowCollection $notificationSends): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'notifications_sent_total',
            'Total number of notification sends by kind, channel, and outcome status.',
            ['kind', 'channel', 'status'],
        );

        foreach ($notificationSends as $row) {
            $counter->incBy($row->total, [$row->kind, $row->channel, $row->status]);
        }
    }
}
