<?php

declare(strict_types=1);

namespace App\Metrics\Application\RemoteWrite;

use App\Connection\Domain\Entity\Connection;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Metrics\Domain\RemoteWrite\Collection\LabelCollection;
use App\Metrics\Domain\RemoteWrite\Collection\SampleCollection;
use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use App\Probe\Domain\Entity\Probe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MeasurementTimeSeriesMapper
{
    /** @var array<string, string> */
    private array $extraLabels;

    public function __construct(#[Autowire('%netpulse.remote_write.extra_labels%')] string $extraLabelsRaw)
    {
        $this->extraLabels = $this->parseExtraLabels($extraLabelsRaw);
    }

    public function map(Measurement $measurement, Connection $connection, Probe $probe): TimeSeriesCollection
    {
        $timestampMs = (int) $measurement->completedAt()->format('Uv');

        $server = $measurement->server();

        $baseLabels = [
            new Label('probe', $probe->name()),
            new Label('connection', $connection->name()),
            new Label('site', $probe->labels()->get('site') ?? ''),
            new Label('server_name', $server->serverName),
            new Label('server_id', $server->serverId),
            new Label('isp', $server->isp),
        ];

        foreach ($this->extraLabels as $name => $value) {
            $baseLabels[] = new Label($name, $value);
        }

        $isUp = $measurement->status() === MeasurementStatus::Completed;

        $series = [
            $this->gauge('netpulse_up', $isUp ? 1.0 : 0.0, $timestampMs, $baseLabels),
            $this->gauge(
                'netpulse_last_result_timestamp_seconds',
                (float) $measurement->completedAt()->getTimestamp(),
                $timestampMs,
                $baseLabels,
            ),
        ];

        if (!$isUp) {
            return TimeSeriesCollection::fromList($series);
        }

        $bandwidth = $measurement->bandwidth();
        $latency = $measurement->latency();
        $packetLoss = $measurement->packetLoss();

        if ($bandwidth !== null) {
            $series[] = $this->gauge(
                'netpulse_download_bits_per_second',
                (float) $bandwidth->downloadBits,
                $timestampMs,
                $baseLabels,
            );
            $series[] = $this->gauge(
                'netpulse_upload_bits_per_second',
                (float) $bandwidth->uploadBits,
                $timestampMs,
                $baseLabels,
            );
            $series[] = $this->gauge(
                'netpulse_data_used_bytes',
                (float) ($bandwidth->downloadBytes + $bandwidth->uploadBytes),
                $timestampMs,
                $baseLabels,
            );
        }

        if ($latency !== null) {
            $series[] = $this->gauge('netpulse_ping_seconds', $latency->ping / 1000.0, $timestampMs, $baseLabels);
            $series[] = $this->gauge('netpulse_jitter_seconds', $latency->jitter / 1000.0, $timestampMs, $baseLabels);
            $series[] = $this->gauge(
                'netpulse_download_latency_iqm_seconds',
                $latency->downloadLatencyIqm / 1000.0,
                $timestampMs,
                $baseLabels,
            );
            $series[] = $this->gauge(
                'netpulse_upload_latency_iqm_seconds',
                $latency->uploadLatencyIqm / 1000.0,
                $timestampMs,
                $baseLabels,
            );
        }

        if ($packetLoss !== null) {
            $series[] = $this->gauge('netpulse_packet_loss_ratio', $packetLoss->ratio, $timestampMs, $baseLabels);
        }

        return TimeSeriesCollection::fromList($series);
    }

    /**
     * @return array<string, string>
     */
    private function parseExtraLabels(string $raw): array
    {
        $extraLabels = [];

        foreach (array_filter(explode(',', $raw)) as $pair) {
            $parts = explode('=', $pair, 2);

            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $extraLabels[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $extraLabels;
    }

    /**
     * @param list<Label> $baseLabels
     */
    private function gauge(string $name, float $value, int $timestampMs, array $baseLabels): TimeSeries
    {
        return new TimeSeries(LabelCollection::fromList([
            new Label('__name__', $name),
            ...$baseLabels,
        ]), SampleCollection::of(new Sample($value, $timestampMs)));
    }
}
