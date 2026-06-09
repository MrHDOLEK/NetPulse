<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Dashboard\Application\Format\UnitFormatter;
use App\Dashboard\Application\ReadModel\MeasurementDetail;
use DateTimeImmutable;
use DateTimeZone;

use function ucfirst;

final readonly class MeasurementDetailResponse
{
    /**
     * @param array<string,mixed> $rawPayload
     */
    private function __construct(
        private MeasurementDetail $detail,
        private string $completedAtIso,
        private string $startedAtIso,
        private array $rawPayload,
    ) {}

    public static function from(MeasurementDetail $detail): self
    {
        $utc = new DateTimeZone("UTC");

        return new self(
            $detail,
            (new DateTimeImmutable("@" . $detail->completedAtUnix))->setTimezone($utc)->format(DateTimeImmutable::ATOM),
            (new DateTimeImmutable("@" . $detail->startedAtUnix))->setTimezone($utc)->format(DateTimeImmutable::ATOM),
            $detail->rawPayload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $detail = $this->detail;

        return [
            "id" => $detail->id->toString(),

            "completedAtUnix" => $detail->completedAtUnix,
            "startedAtUnix" => $detail->startedAtUnix,
            "completedAt" => $this->completedAtIso,
            "startedAt" => $this->startedAtIso,

            "connection" => $detail->connectionName,
            "color" => $detail->connectionColor->value,
            "isp" => $detail->isp,
            "serverId" => $detail->serverId,
            "serverName" => $detail->serverName,
            "serverLocation" => $detail->serverLocation,
            "serverHost" => $detail->serverHost,

            "status" => $detail->status->value,
            "statusLabel" => ucfirst($detail->status->value),
            "failReason" => $detail->failReason,
            "scheduled" => $detail->scheduled,
            "healthy" => $detail->healthy,
            "resultUrl" => $detail->resultUrl,

            "downloadBits" => $detail->downloadBits,
            "uploadBits" => $detail->uploadBits,
            "pingSeconds" => $detail->pingSeconds,
            "pingLowSeconds" => $detail->pingLowSeconds,
            "pingHighSeconds" => $detail->pingHighSeconds,
            "jitterSeconds" => $detail->jitterSeconds,
            "downloadLatencyIqmSeconds" => $detail->downloadLatencyIqmSeconds,
            "uploadLatencyIqmSeconds" => $detail->uploadLatencyIqmSeconds,
            "packetLossRatio" => $detail->packetLossRatio,
            "dataUsedDownload" => $detail->dataUsedDownload,
            "dataUsedUpload" => $detail->dataUsedUpload,

            "downloadLabel" => UnitFormatter::bitsPerSecond($detail->downloadBits),
            "uploadLabel" => UnitFormatter::bitsPerSecond($detail->uploadBits),
            "pingLabel" => UnitFormatter::seconds($detail->pingSeconds),
            "pingLowLabel" => UnitFormatter::seconds($detail->pingLowSeconds),
            "pingHighLabel" => UnitFormatter::seconds($detail->pingHighSeconds),
            "jitterLabel" => UnitFormatter::seconds($detail->jitterSeconds),
            "downloadLatencyIqmLabel" => UnitFormatter::seconds($detail->downloadLatencyIqmSeconds),
            "uploadLatencyIqmLabel" => UnitFormatter::seconds($detail->uploadLatencyIqmSeconds),
            "lossLabel" => UnitFormatter::ratio($detail->packetLossRatio),

            "rawPayload" => $this->rawPayload,
        ];
    }
}
