<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\ConnectionListItem;
use App\Dashboard\Application\ReadModel\ConnectionListRepository;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlConnectionListRepositoryTest extends KernelTestCase
{
    private const string PROBE_HOME = '11111111-1111-1111-1111-111111111111';
    private const string PROBE_OFFICE = '22222222-2222-2222-2222-222222222222';
    private const string CONN_HOME_A = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CONN_HOME_B = 'aaaaaaaa-0000-0000-0000-000000000002';
    private const string CONN_OFFICE_A = 'bbbbbbbb-0000-0000-0000-000000000001';

    private DbalConnection $db;
    private ConnectionListRepository $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(ConnectionListRepository::class);
        $this->seed();
    }

    public function testAllReturnsOneItemPerConnectionWithProjectedFields(): void
    {
        $items = $this->readModel->all();

        self::assertCount(3, $items);

        $byName = [];

        foreach ($items as $item) {
            self::assertInstanceOf(ConnectionListItem::class, $item);
            $byName[$item->name] = $item;
        }

        $wan1 = $byName['wan1'];
        self::assertInstanceOf(ConnectionId::class, $wan1->connectionId);
        self::assertSame(self::CONN_HOME_A, $wan1->connectionId->toString());
        self::assertSame('wan1', $wan1->name);
        self::assertSame('Acme ISP', $wan1->isp);
        self::assertSame(ConnectionColor::Primary, $wan1->color);
        self::assertSame(1_000_000_000, $wan1->expectedDownloadBits);
        self::assertSame('home', $wan1->probeName);

        $wan2 = $byName['wan2'];
        self::assertSame(ConnectionColor::Violet, $wan2->color);
        self::assertSame(600_000_000, $wan2->expectedDownloadBits);
        self::assertSame('home', $wan2->probeName);

        $uplink = $byName['uplink'];
        self::assertSame(ConnectionColor::Amber, $uplink->color);
        self::assertSame(300_000_000, $uplink->expectedDownloadBits);
        self::assertSame('office', $uplink->probeName);
    }

    public function testAllIsOrderedByProbeNameThenConnectionName(): void
    {
        $names = [];

        foreach ($this->readModel->all() as $item) {
            $names[] = $item->probeName . '/' . $item->name;
        }

        self::assertSame(['home/wan1', 'home/wan2', 'office/uplink'], $names);
    }

    private function seed(): void
    {
        $this->insertProbe(self::PROBE_HOME, 'home');
        $this->insertProbe(self::PROBE_OFFICE, 'office');

        $this->insertConnection(self::CONN_HOME_B, self::PROBE_HOME, 'wan2', 'violet', 600_000_000);
        $this->insertConnection(self::CONN_OFFICE_A, self::PROBE_OFFICE, 'uplink', 'amber', 300_000_000);
        $this->insertConnection(self::CONN_HOME_A, self::PROBE_HOME, 'wan1', 'primary', 1_000_000_000);
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

    private function insertConnection(
        string $id,
        string $probeId,
        string $name,
        string $color,
        int $expectedDownloadBits,
    ): void {
        $this->db->insert('connections', [
            'id' => $id,
            'probe_id' => $probeId,
            'name' => $name,
            'isp' => 'Acme ISP',
            'expected_download_bits' => $expectedDownloadBits,
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
}
