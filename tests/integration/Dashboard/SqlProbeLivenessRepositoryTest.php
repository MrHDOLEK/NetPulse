<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\Application\ReadModel\ProbeLivenessRepository;
use Doctrine\DBAL\Connection as DbalConnection;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

use function in_array;

final class SqlProbeLivenessRepositoryTest extends KernelTestCase
{
    private const string NOW = '2026-06-07 12:00:00';
    private const string ONLINE_PROBE = '11111111-1111-1111-1111-111111111111';
    private const string OFFLINE_PROBE = '22222222-2222-2222-2222-222222222222';
    private const string NEVER_PROBE = '33333333-3333-3333-3333-333333333333';

    private DbalConnection $db;
    private ProbeLivenessRepository $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $container->set(ClockInterface::class, new MockClock(self::NOW));

        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->readModel = $container->get(ProbeLivenessRepository::class);

        foreach ([self::ONLINE_PROBE, self::OFFLINE_PROBE, self::NEVER_PROBE] as $id) {
            $this->db->executeStatement('DELETE FROM probes WHERE id = :id', ['id' => $id]);
        }

        $this->insertProbe(self::ONLINE_PROBE, 'edge-a', '2026-06-01 09:00:00', '2026-06-07 11:58:00');

        $this->insertProbe(self::OFFLINE_PROBE, 'edge-b', '2026-06-02 09:00:00', '2026-06-07 11:50:00');

        $this->insertProbe(self::NEVER_PROBE, 'edge-c', '2026-06-03 09:00:00', null);
    }

    public function testDerivesOnlineOfflineAndNeverPolledFromLastPollAt(): void
    {
        $byId = [];

        foreach ($this->readModel->all() as $liveness) {
            $byId[$liveness->probeId] = $liveness;
        }

        self::assertTrue($byId[self::ONLINE_PROBE]->isOnline);
        self::assertSame(2, $byId[self::ONLINE_PROBE]->minutesSincePoll);

        self::assertFalse($byId[self::OFFLINE_PROBE]->isOnline);
        self::assertSame(10, $byId[self::OFFLINE_PROBE]->minutesSincePoll);

        self::assertFalse($byId[self::NEVER_PROBE]->isOnline);
        self::assertNull($byId[self::NEVER_PROBE]->lastPollAtUnix);
        self::assertNull($byId[self::NEVER_PROBE]->minutesSincePoll);
    }

    public function testListsProbesNewestFirst(): void
    {
        $ids = [];

        foreach ($this->readModel->all() as $liveness) {
            if (in_array($liveness->probeId, [self::ONLINE_PROBE, self::OFFLINE_PROBE, self::NEVER_PROBE], true)) {
                $ids[] = $liveness->probeId;
            }
        }

        self::assertSame([self::NEVER_PROBE, self::OFFLINE_PROBE, self::ONLINE_PROBE], $ids);
    }

    private function insertProbe(string $id, string $name, string $createdAt, ?string $lastPollAt): void
    {
        $this->db->insert('probes', [
            'id' => $id,
            'name' => $name,
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => $createdAt,
            'last_poll_at' => $lastPollAt,
        ]);
    }
}
