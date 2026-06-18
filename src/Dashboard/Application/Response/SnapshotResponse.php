<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Response;

use App\Dashboard\Application\ReadModel\ConnectionOverview;
use App\Dashboard\Application\ReadModel\ConnectionOverviewCollection;

final readonly class SnapshotResponse
{
    /**
     * @param list<array<string, string|int|float|bool|null>> $connections
     */
    private function __construct(
        public array $connections,
    ) {}

    public static function fromOverview(ConnectionOverviewCollection $overview): self
    {
        $connections = [];

        foreach ($overview as $connection) {
            $connections[] = self::snapshot($connection);
        }

        return new self($connections);
    }

    /**
     * @return array{
     *     connections: list<array<string, string|int|float|bool|null>>,
     *     prometheus: array{status: string, endpoint: string},
     * }
     */
    public function toArray(): array
    {
        return [
            'connections' => $this->connections,
            'prometheus' => [
                'status' => 'scraping',
                'endpoint' => '/metrics',
            ],
        ];
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private static function snapshot(ConnectionOverview $connection): array
    {
        return [
            'connectionId' => $connection->connectionId->toString(),
            'name' => $connection->name,
            'status' => $connection->status->value,
            'downloadBits' => $connection->downloadBits,
            'uploadBits' => $connection->uploadBits,
            'pingSeconds' => $connection->pingSeconds,
            'packetLossRatio' => $connection->packetLossRatio,
            'uptimePct' => $connection->uptimePct,
            'latestHealthy' => $connection->latestHealthy,
            'completedAtUnix' => $connection->completedAtUnix,
        ];
    }
}
