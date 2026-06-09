<?php

declare(strict_types=1);

namespace App\Measurement\Application\Ookla;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\Bandwidth;
use App\Measurement\Domain\ValueObject\Latency;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Measurement\Domain\ValueObject\PacketLoss;
use App\Measurement\Domain\ValueObject\ServerInfo;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OoklaResultMapper::class)]
final readonly class DefaultOoklaResultMapper implements OoklaResultMapper
{
    /**
     * @param array<string,mixed> $rawPayload
     */
    public function toMeasurement(
        MeasurementId $id,
        ProbeId $probeId,
        ConnectionId $connectionId,
        OoklaResult $result,
        bool $scheduled,
        DateTimeImmutable $recordedAt,
        array $rawPayload,
    ): Measurement {
        $server = $this->server($result);

        if (!$result->isCompleted()) {
            return new Measurement(
                $id,
                $probeId,
                $connectionId,
                MeasurementStatus::Failed,
                $scheduled,
                $recordedAt,
                $recordedAt,
                $server,
                null,
                null,
                null,
                0,
                0,
                0,
                0,
                null,
                $rawPayload,
            );
        }

        $bandwidth = $this->bandwidth($result);
        $latency = $this->latency($result);
        $packetLoss = PacketLoss::fromOoklaPercent($result->packetLoss ?? 0.0);

        return new Measurement(
            $id,
            $probeId,
            $connectionId,
            MeasurementStatus::Completed,
            $scheduled,
            $recordedAt,
            $recordedAt,
            $server,
            $bandwidth,
            $latency,
            $packetLoss,
            $bandwidth->downloadBytes,
            $bandwidth->uploadBytes,
            (int)($result->download->elapsed ?? 0),
            (int)($result->upload->elapsed ?? 0),
            $result->result?->url,
            $rawPayload,
        );
    }

    private function server(OoklaResult $result): ServerInfo
    {
        $server = $result->server;

        $host = $server->host ?? "";
        $port = $server?->port !== null ? (string)$server->port : "";

        if ($port !== "" && $host !== "") {
            $host .= ":" . $port;
        }

        return new ServerInfo(
            serverId: $server?->id !== null ? (string)$server->id : "",
            serverName: $server->name ?? "",
            serverLocation: $server->location ?? "",
            serverHost: $host,
            isp: $result->isp ?? "",
        );
    }

    private function bandwidth(OoklaResult $result): Bandwidth
    {
        $download = $result->download;
        $upload = $result->upload;

        return new Bandwidth(
            downloadBits: (int)($download->bandwidth ?? 0) * 8,
            uploadBits: (int)($upload->bandwidth ?? 0) * 8,
            downloadBytes: (int)($download->bytes ?? 0),
            uploadBytes: (int)($upload->bytes ?? 0),
        );
    }

    private function latency(OoklaResult $result): Latency
    {
        $ping = $result->ping;
        $downloadLatency = $result->download?->latency;
        $uploadLatency = $result->upload?->latency;

        return new Latency(
            ping: $ping->latency ?? 0.0,
            pingLow: $ping->low ?? 0.0,
            pingHigh: $ping->high ?? 0.0,
            jitter: $ping->jitter ?? 0.0,
            downloadJitter: $downloadLatency->jitter ?? 0.0,
            uploadJitter: $uploadLatency->jitter ?? 0.0,
            downloadLatencyIqm: $downloadLatency->iqm ?? 0.0,
            downloadLatencyLow: $downloadLatency->low ?? 0.0,
            downloadLatencyHigh: $downloadLatency->high ?? 0.0,
            uploadLatencyIqm: $uploadLatency->iqm ?? 0.0,
            uploadLatencyLow: $uploadLatency->low ?? 0.0,
            uploadLatencyHigh: $uploadLatency->high ?? 0.0,
        );
    }
}
