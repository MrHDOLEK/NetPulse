<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Dashboard\Application\Format\UnitFormatter;
use App\Dashboard\Application\ReadModel\MeasurementListItem;
use App\Dashboard\Application\ReadModel\MeasurementListItemCollection;

final readonly class MeasurementListResponse
{
    /**
     * @param list<array<string, string|int|float|bool|null>> $items
     */
    private function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {}

    public static function from(MeasurementListItemCollection $items, int $total, int $limit, int $offset): self
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = self::item($item);
        }

        return new self($rows, $total, $limit, $offset);
    }

    /**
     * @return array{
     *     items: list<array<string, string|int|float|bool|null>>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     * }
     */
    public function toArray(): array
    {
        return [
            "items" => $this->items,
            "total" => $this->total,
            "limit" => $this->limit,
            "offset" => $this->offset,
        ];
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private static function item(MeasurementListItem $item): array
    {
        return [
            "id" => $item->id->toString(),
            "t" => $item->completedAtUnix,

            "completedAt" => gmdate("Y-m-d H:i", $item->completedAtUnix),
            "status" => $item->status->value,
            "statusLabel" => ucfirst($item->status->value),
            "connection" => $item->connectionName,
            "color" => $item->connectionColor->value,
            "isp" => $item->isp,
            "server" => $item->serverName,
            "location" => $item->serverLocation,
            "dl" => $item->downloadBits,
            "up" => $item->uploadBits,
            "ping" => $item->pingSeconds,
            "jitter" => $item->jitterSeconds,
            "loss" => $item->packetLossRatio,
            "healthy" => $item->healthy,
            "scheduled" => $item->scheduled,
            "downloadLabel" => UnitFormatter::bitsPerSecond($item->downloadBits),
            "uploadLabel" => UnitFormatter::bitsPerSecond($item->uploadBits),
            "pingLabel" => UnitFormatter::seconds($item->pingSeconds),
            "jitterLabel" => UnitFormatter::seconds($item->jitterSeconds),
            "lossLabel" => UnitFormatter::ratio($item->packetLossRatio),
        ];
    }
}
