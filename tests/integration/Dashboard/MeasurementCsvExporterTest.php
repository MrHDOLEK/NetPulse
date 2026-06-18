<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\Export\MeasurementCsvExporter;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use App\Measurement\Domain\Enum\MeasurementStatus;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;
use function iterator_to_array;

final class MeasurementCsvExporterTest extends KernelTestCase
{
    private const string PROBE = '22222222-2222-2222-2222-222222222222';
    private const string CONN = 'cccccccc-0000-0000-0000-000000000001';
    private const string SERVER_WARSAW = '100';
    private const string WINDOW_START = '2026-06-01 00:00:00';
    private const string NOW = '2026-06-08 00:00:00';

    private DbalConnection $db;
    private MeasurementListRepository $readModel;
    private int $windowStartUnix;
    private int $nowUnix;
    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(MeasurementListRepository::class);

        $this->windowStartUnix = new DateTimeImmutable(self::WINDOW_START, new DateTimeZone('UTC'))->getTimestamp();
        $this->nowUnix = new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'))->getTimestamp();

        $this->seed();
    }

    public function testHeaderIsTheFixedColumnList(): void
    {
        $exporter = new MeasurementCsvExporter($this->readModel, new RecordingLogger());

        self::assertSame(
            [
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
            ],
            $exporter->header(),
        );
    }

    public function testRowsAreNewestFirstAndFullyFormatted(): void
    {
        $logger = new RecordingLogger();
        $exporter = new MeasurementCsvExporter($this->readModel, $logger);

        $rows = iterator_to_array($exporter->rows($this->windowFilter()), false);

        self::assertCount(7, $rows);

        $completedAt = array_map(static fn(array $row): string => $row[1], $rows);
        $sorted = $completedAt;
        rsort($sorted);
        self::assertSame($sorted, $completedAt);

        $newest = $rows[0];
        self::assertSame('935', $newest[7]);
        self::assertSame('9.5', $newest[8]);
        self::assertSame('50', $newest[9]);
        self::assertSame('12.3', $newest[10]);
        self::assertSame('2.5', $newest[11]);
        self::assertSame(MeasurementStatus::Completed->value, $newest[12]);
        self::assertSame('1', $newest[6]);
        self::assertSame('1', $newest[13]);
        self::assertSame('', $newest[14]);
        self::assertSame('wan-export', $newest[2]);
        self::assertSame('Export ISP', $newest[3]);
        self::assertSame('Acme Speedtest', $newest[4]);
        self::assertSame('Warsaw', $newest[5]);

        $expectedCompletedAt = gmdate('c', $this->nowUnix - 10);
        self::assertSame($expectedCompletedAt, $newest[1]);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $newest[0],
        );

        $failedRows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row[12] === MeasurementStatus::Failed->value,
        ));
        self::assertNotEmpty($failedRows);

        foreach ($failedRows as $row) {
            self::assertSame('', $row[7]);
            self::assertSame('', $row[8]);
            self::assertSame('', $row[9]);
            self::assertSame('', $row[10]);
            self::assertSame('', $row[11]);
            self::assertSame('', $row[13]);
            self::assertSame('', $row[14]);
        }

        self::assertFalse($logger->has('warning', 'csv export truncated'));
    }

    public function testHealthyFalseAndZeroLossFormatExplicitly(): void
    {
        $exporter = new MeasurementCsvExporter($this->readModel, new RecordingLogger());

        $rows = iterator_to_array($exporter->rows($this->windowFilter()), false);

        $unhealthy = array_values(array_filter($rows, static fn(array $row): bool => $row[13] === '0'));
        self::assertNotEmpty($unhealthy);
        self::assertSame('0', $unhealthy[0][13]);
        self::assertSame('0', $unhealthy[0][6]);
        self::assertSame('0', $unhealthy[0][11]);
    }

    public function testCapSmallerThanMatchCountTruncatesAndWarnsOnce(): void
    {
        $logger = new RecordingLogger();
        $exporter = new MeasurementCsvExporter($this->readModel, $logger);

        $rows = iterator_to_array($exporter->rows($this->windowFilter(), 3), false);

        self::assertCount(3, $rows);

        $completedAt = array_map(static fn(array $row): string => $row[1], $rows);
        $sorted = $completedAt;
        rsort($sorted);
        self::assertSame($sorted, $completedAt);

        self::assertTrue($logger->has('warning', 'csv export truncated'));
        self::assertSame(1, $logger->count('warning', 'csv export truncated'));
        self::assertSame(['cap' => 3], $logger->lastContext('warning'));
    }

    public function testCapEqualToMatchCountDoesNotWarn(): void
    {
        $logger = new RecordingLogger();
        $exporter = new MeasurementCsvExporter($this->readModel, $logger);

        $rows = iterator_to_array($exporter->rows($this->windowFilter(), 7), false);

        self::assertCount(7, $rows);
        self::assertFalse($logger->has('warning', 'csv export truncated'));
    }

    public function testStatusFilterExportsOnlyFailedRows(): void
    {
        $logger = new RecordingLogger();
        $exporter = new MeasurementCsvExporter($this->readModel, $logger);

        $filter = new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: MeasurementStatus::Failed,
            healthy: null,
            scheduled: null,
        );

        $rows = iterator_to_array($exporter->rows($filter), false);

        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            self::assertSame(MeasurementStatus::Failed->value, $row[12]);
        }

        self::assertLessThan(7, count($rows));
    }

    private function windowFilter(): MeasurementFilter
    {
        return new MeasurementFilter(
            connection: null,
            since: $this->since(),
            until: $this->until(),
            serverId: null,
            status: null,
            healthy: null,
            scheduled: null,
        );
    }

    private function since(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::WINDOW_START, new DateTimeZone('UTC'));
    }

    private function until(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
    }

    private function seed(): void
    {
        $this->insertProbe(self::PROBE, 'home');
        $this->insertConnection(self::CONN, 'wan-export', 'primary', 'Export ISP');

        $start = $this->windowStartUnix;

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 100,
            900_000_000,
            90_000_000,
            12.0,
            2.0,
            0.00,
            true,
            true,
        );
        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 200,
            800_000_000,
            80_000_000,
            14.0,
            3.0,
            0.01,
            true,
            true,
        );

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 300,
            300_000_000,
            30_000_000,
            60.0,
            12.0,
            0.00,
            false,
            false,
        );

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'failed',
            $start + 400,
            null,
            null,
            null,
            null,
            null,
            null,
            true,
        );
        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'failed',
            $start + 500,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
        );

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start + 600,
            600_000_000,
            60_000_000,
            18.0,
            4.0,
            0.01,
            true,
            true,
        );

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $this->nowUnix - 10,
            935_000_000,
            9_500_000,
            50.0,
            12.3,
            0.025,
            true,
            true,
        );

        $this->insert(
            self::CONN,
            self::SERVER_WARSAW,
            'Warsaw',
            'completed',
            $start - 50,
            999_000_000,
            99_000_000,
            9.0,
            1.0,
            0.00,
            true,
            true,
        );
    }

    private function insertProbe(string $id, string $name): void
    {
        $this->db->insert('probes', [
            'id' => $id,
            'name' => $name,
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);
    }

    private function insertConnection(string $id, string $name, string $color, string $isp): void
    {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => self::PROBE,
            'name' => $name,
            'isp' => $isp,
            'expected_download_bits' => 1_000_000_000,
            'expected_upload_bits' => 500_000_000,
            'color' => $color,
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'server_pool' => json_encode([], JSON_THROW_ON_ERROR),
            'schedule' => json_encode([
                'mode' => 'even',
                'cronExpressions' => [],
                'testsPerDay' => 24,
                'jitterSeconds' => 120,
            ], JSON_THROW_ON_ERROR),
            'thresholds' => json_encode([
                'minDownloadRatio' => 0.7,
                'minUploadRatio' => 0.7,
                'maxPingMs' => 100,
                'maxJitterMs' => 50,
                'maxPacketLossRatio' => 0.05,
            ], JSON_THROW_ON_ERROR),
            'adaptive_policy' => json_encode([
                'adaptiveIntervalSeconds' => 300,
                'recoveryHealthyCount' => 3,
                'maxConsecutiveFailures' => 5,
            ], JSON_THROW_ON_ERROR),
            'enabled' => 1,
        ]);
    }

    private function insert(
        string $connectionId,
        string $serverId,
        string $serverLocation,
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingMs,
        ?float $jitterMs,
        ?float $packetLossRatio,
        ?bool $healthy,
        bool $scheduled,
    ): string {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $id = sprintf('ffffffff-0000-0000-0000-%012d', ++$this->sequence);

        $this->db->insert(
            'measurements',
            [
                'id' => $id,
                'probe_id' => self::PROBE,
                'connection_id' => $connectionId,
                'status' => $status,
                'scheduled' => $scheduled ? 1 : 0,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'server_id' => $serverId,
                'server_name' => 'Acme Speedtest',
                'server_location' => $serverLocation,
                'server_host' => 'speedtest.acme.example:8080',
                'isp' => 'Acme ISP',
                'download_bits' => $downloadBits,
                'upload_bits' => $uploadBits,
                'ping' => $pingMs,
                'jitter' => $jitterMs,
                'packet_loss_ratio' => $packetLossRatio,
                'data_used_download' => 0,
                'data_used_upload' => 0,
                'download_elapsed' => 4000,
                'upload_elapsed' => 4000,
                'raw_payload' => json_encode([], JSON_THROW_ON_ERROR),
                'healthy' => $healthy,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );

        return $id;
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function has(string $level, string $message): bool
    {
        return $this->count($level, $message) > 0;
    }

    public function count(string $level, string $message): int
    {
        $count = 0;

        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastContext(string $level): array
    {
        $context = [];

        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                $context = $record['context'];
            }
        }

        return $context;
    }
}
