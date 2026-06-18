<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Export;

use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListItem;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use Psr\Log\LoggerInterface;

use function number_format;
use function rtrim;

final readonly class MeasurementCsvExporter
{
    private const int CHUNK_SIZE = 1000;
    private const int DEFAULT_CAP = 50000;

    public function __construct(
        private MeasurementListRepository $list,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return [
            'id',
            'completed_at',
            'connection_name',
            'connection_isp',
            'server_name',
            'server_location',
            'scheduled',
            'download_mbps',
            'upload_mbps',
            'ping_ms',
            'jitter_ms',
            'packet_loss_pct',
            'status',
            'healthy',
            'fail_reason',
        ];
    }

    /**
     * @return iterable<list<string>>
     */
    public function rows(MeasurementFilter $filter, int $cap = self::DEFAULT_CAP): iterable
    {
        $offset = 0;
        $emitted = 0;

        while ($emitted < $cap) {
            $chunk = $this->list->list($filter, self::CHUNK_SIZE, $offset, MeasurementSort::CompletedAtDesc);

            if ($chunk->isEmpty()) {
                return;
            }

            foreach ($chunk as $item) {
                if ($emitted >= $cap) {
                    $this->warnTruncated($cap);

                    return;
                }

                yield $this->row($item);
                ++$emitted;
            }

            $offset += self::CHUNK_SIZE;
        }

        if ($this->list->countMatching($filter) > $cap) {
            $this->warnTruncated($cap);
        }
    }

    /**
     * @return list<string>
     */
    private function row(MeasurementListItem $item): array
    {
        return [
            $item->id->toString(),
            gmdate('c', $item->completedAtUnix),
            $item->connectionName,
            $item->isp,
            $item->serverName,
            $item->serverLocation,
            $item->scheduled ? '1' : '0',
            $this->number($item->downloadBits === null ? null : $item->downloadBits / 1e6),
            $this->number($item->uploadBits === null ? null : $item->uploadBits / 1e6),
            $this->number($item->pingSeconds === null ? null : $item->pingSeconds * 1000),
            $this->number($item->jitterSeconds === null ? null : $item->jitterSeconds * 1000),
            $this->number($item->packetLossRatio === null ? null : $item->packetLossRatio * 100),
            $item->status->value,
            $this->bool($item->healthy),
            '',
        ];
    }

    private function number(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private function bool(?bool $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? '1' : '0';
    }

    private function warnTruncated(int $cap): void
    {
        $this->logger->warning('csv export truncated', [
            'cap' => $cap,
        ]);
    }
}
