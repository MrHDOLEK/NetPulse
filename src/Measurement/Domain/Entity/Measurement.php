<?php

declare(strict_types=1);

namespace App\Measurement\Domain\Entity;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\ValueObject\Bandwidth;
use App\Measurement\Domain\ValueObject\Latency;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Measurement\Domain\ValueObject\PacketLoss;
use App\Measurement\Domain\ValueObject\ServerInfo;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\ShareToken;
use DateTimeImmutable;

class Measurement
{
    private string $serverId = '';
    private string $serverName = '';
    private string $serverLocation = '';
    private string $serverHost = '';
    private string $isp = '';
    private ?int $downloadBits = null;
    private ?int $uploadBits = null;
    private ?int $downloadBytes = null;
    private ?int $uploadBytes = null;
    private ?float $ping = null;
    private ?float $pingLow = null;
    private ?float $pingHigh = null;
    private ?float $jitter = null;
    private ?float $downloadJitter = null;
    private ?float $uploadJitter = null;
    private ?float $downloadLatencyIqm = null;
    private ?float $downloadLatencyLow = null;
    private ?float $downloadLatencyHigh = null;
    private ?float $uploadLatencyIqm = null;
    private ?float $uploadLatencyLow = null;
    private ?float $uploadLatencyHigh = null;
    private ?float $packetLossRatio = null;
    private ?bool $healthy = null;
    private ?string $shareToken = null;

    /**
     * @param array<string,mixed> $rawPayload
     */
    public function __construct(
        private readonly MeasurementId $id,
        private readonly ProbeId $probeId,
        private readonly ConnectionId $connectionId,
        private readonly MeasurementStatus $status,
        private readonly bool $scheduled,
        private readonly DateTimeImmutable $startedAt,
        private readonly DateTimeImmutable $completedAt,
        ServerInfo $server,
        ?Bandwidth $bandwidth,
        ?Latency $latency,
        ?PacketLoss $packetLoss,
        private readonly int $dataUsedDownload,
        private readonly int $dataUsedUpload,
        private readonly int $downloadElapsed,
        private readonly int $uploadElapsed,
        private readonly ?string $resultUrl,
        private readonly array $rawPayload,
    ) {
        $this->serverId = $server->serverId;
        $this->serverName = $server->serverName;
        $this->serverLocation = $server->serverLocation;
        $this->serverHost = $server->serverHost;
        $this->isp = $server->isp;

        if ($bandwidth !== null) {
            $this->downloadBits = $bandwidth->downloadBits;
            $this->uploadBits = $bandwidth->uploadBits;
            $this->downloadBytes = $bandwidth->downloadBytes;
            $this->uploadBytes = $bandwidth->uploadBytes;
        }

        if ($latency !== null) {
            $this->ping = $latency->ping;
            $this->pingLow = $latency->pingLow;
            $this->pingHigh = $latency->pingHigh;
            $this->jitter = $latency->jitter;
            $this->downloadJitter = $latency->downloadJitter;
            $this->uploadJitter = $latency->uploadJitter;
            $this->downloadLatencyIqm = $latency->downloadLatencyIqm;
            $this->downloadLatencyLow = $latency->downloadLatencyLow;
            $this->downloadLatencyHigh = $latency->downloadLatencyHigh;
            $this->uploadLatencyIqm = $latency->uploadLatencyIqm;
            $this->uploadLatencyLow = $latency->uploadLatencyLow;
            $this->uploadLatencyHigh = $latency->uploadLatencyHigh;
        }

        if ($packetLoss !== null) {
            $this->packetLossRatio = $packetLoss->ratio;
        }
    }

    public function id(): MeasurementId
    {
        return $this->id;
    }

    public function probeId(): ProbeId
    {
        return $this->probeId;
    }

    public function connectionId(): ConnectionId
    {
        return $this->connectionId;
    }

    public function status(): MeasurementStatus
    {
        return $this->status;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function server(): ServerInfo
    {
        return new ServerInfo($this->serverId, $this->serverName, $this->serverLocation, $this->serverHost, $this->isp);
    }

    public function bandwidth(): ?Bandwidth
    {
        if (
            $this->downloadBits === null
            || $this->uploadBits === null
            || $this->downloadBytes === null
            || $this->uploadBytes === null
        ) {
            return null;
        }

        return new Bandwidth($this->downloadBits, $this->uploadBits, $this->downloadBytes, $this->uploadBytes);
    }

    public function latency(): ?Latency
    {
        if ($this->ping === null) {
            return null;
        }

        return new Latency(
            $this->ping,
            (float) $this->pingLow,
            (float) $this->pingHigh,
            (float) $this->jitter,
            (float) $this->downloadJitter,
            (float) $this->uploadJitter,
            (float) $this->downloadLatencyIqm,
            (float) $this->downloadLatencyLow,
            (float) $this->downloadLatencyHigh,
            (float) $this->uploadLatencyIqm,
            (float) $this->uploadLatencyLow,
            (float) $this->uploadLatencyHigh,
        );
    }

    public function packetLoss(): ?PacketLoss
    {
        if ($this->packetLossRatio === null) {
            return null;
        }

        return new PacketLoss($this->packetLossRatio);
    }

    public function dataUsedDownload(): int
    {
        return $this->dataUsedDownload;
    }

    public function dataUsedUpload(): int
    {
        return $this->dataUsedUpload;
    }

    public function downloadElapsed(): int
    {
        return $this->downloadElapsed;
    }

    public function uploadElapsed(): int
    {
        return $this->uploadElapsed;
    }

    public function resultUrl(): ?string
    {
        return $this->resultUrl;
    }

    public function healthy(): ?bool
    {
        return $this->healthy;
    }

    public function shareToken(): ?string
    {
        return $this->shareToken;
    }

    public function share(): string
    {
        return $this->shareToken ??= ShareToken::generate()->toString();
    }

    public function markHealth(bool $healthy): void
    {
        $this->healthy = $healthy;
    }

    /**
     * @return array<string,mixed>
     */
    public function rawPayload(): array
    {
        return $this->rawPayload;
    }
}
