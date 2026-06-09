<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Dashboard\Application\Format\RelativeTime;
use App\Dashboard\Application\Format\UnitFormatter;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRow;
use App\Dashboard\Application\ReadModel\ServerMetricsRowCollection;

final readonly class ServerMetricsResponse
{
    /**
     * @param list<array{
     *     serverId: string,
     *     name: string,
     *     location: string,
     *     download: float|null,
     *     downloadLabel: string,
     *     upload: float|null,
     *     uploadLabel: string,
     *     ping: float|null,
     *     pingLabel: string,
     *     loss: float|null,
     *     lossLabel: string,
     *     tests: int,
     *     healthPct: float|null,
     *     healthLabel: string,
     *     lastSeenUnix: int,
     *     lastSeenLabel: string
     * }> $rows
     */
    private function __construct(
        public string $window,
        public array $rows,
    ) {}

    public static function from(ServerMetricsRowCollection $rows, HeatmapWindow $window, int $nowUnix): self
    {
        $payloadRows = [];

        foreach ($rows as $row) {
            $payloadRows[] = self::row($row, $nowUnix);
        }

        return new self($window->value, $payloadRows);
    }

    /**
     * @return array{
     *     window: string,
     *     rows: list<array{
     *         serverId: string,
     *         name: string,
     *         location: string,
     *         download: float|null,
     *         downloadLabel: string,
     *         upload: float|null,
     *         uploadLabel: string,
     *         ping: float|null,
     *         pingLabel: string,
     *         loss: float|null,
     *         lossLabel: string,
     *         tests: int,
     *         healthPct: float|null,
     *         healthLabel: string,
     *         lastSeenUnix: int,
     *         lastSeenLabel: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            "window" => $this->window,
            "rows" => $this->rows,
        ];
    }

    /**
     * @return array{
     *     serverId: string,
     *     name: string,
     *     location: string,
     *     download: float|null,
     *     downloadLabel: string,
     *     upload: float|null,
     *     uploadLabel: string,
     *     ping: float|null,
     *     pingLabel: string,
     *     loss: float|null,
     *     lossLabel: string,
     *     tests: int,
     *     healthPct: float|null,
     *     healthLabel: string,
     *     lastSeenUnix: int,
     *     lastSeenLabel: string
     * }
     */
    private static function row(ServerMetricsRow $row, int $nowUnix): array
    {
        $healthRatio = $row->testCount > 0 ? $row->healthyCount / $row->testCount : null;

        return [
            "serverId" => $row->serverId,
            "name" => $row->name,
            "location" => $row->location,
            "download" => $row->avgDownloadBits,
            "downloadLabel" => UnitFormatter::bitsPerSecond(self::bits($row->avgDownloadBits)),
            "upload" => $row->avgUploadBits,
            "uploadLabel" => UnitFormatter::bitsPerSecond(self::bits($row->avgUploadBits)),
            "ping" => $row->avgPingSeconds,
            "pingLabel" => UnitFormatter::seconds($row->avgPingSeconds),
            "loss" => $row->avgLossRatio,
            "lossLabel" => UnitFormatter::ratio($row->avgLossRatio),
            "tests" => $row->testCount,

            "healthPct" => $healthRatio === null ? null : (float)($healthRatio * 100),
            "healthLabel" => UnitFormatter::ratio($healthRatio),
            "lastSeenUnix" => $row->lastSeenUnix,
            "lastSeenLabel" => RelativeTime::fromUnix($row->lastSeenUnix, $nowUnix),
        ];
    }

    private static function bits(?float $value): ?int
    {
        return $value === null ? null : (int)round($value);
    }
}
