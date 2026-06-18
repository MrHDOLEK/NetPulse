<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Dashboard\Application\ReadModel\PendingRunsRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqlPendingRunsRepositoryTest extends KernelTestCase
{
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN_RUNNING = 'aaaaaaaa-0000-7000-8000-000000000001';
    private const string CONN_DONE = 'aaaaaaaa-0000-7000-8000-000000000002';

    private DbalConnection $dbal;
    private ConnectionRepository $connections;
    private PendingRunsRepository $pendingRuns;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->dbal = $container->get(DbalConnection::class);
        $this->connections = $container->get(ConnectionRepository::class);
        $this->pendingRuns = $container->get(PendingRunsRepository::class);

        $this->dbal->executeStatement('DELETE FROM run_states');
        $this->dbal->executeStatement('DELETE FROM connections');
    }

    public function testReturnsRunningWithConnectionNameButExcludesDone(): void
    {
        $this->connections->save($this->connection(self::CONN_RUNNING, 'wan', ConnectionColor::Amber));
        $this->connections->save($this->connection(self::CONN_DONE, 'lan', ConnectionColor::Violet));
        $this->insertRunState(self::CONN_RUNNING, 'running');
        $this->insertRunState(self::CONN_DONE, 'done');

        $pending = $this->pendingRuns->pending();

        self::assertCount(1, $pending);
        self::assertSame(self::CONN_RUNNING, $pending[0]->connectionId);
        self::assertSame('wan', $pending[0]->connectionName);
        self::assertSame('amber', $pending[0]->color);
        self::assertSame('running', $pending[0]->phase);
    }

    public function testEmptyWhenNothingIsInFlight(): void
    {
        $this->connections->save($this->connection(self::CONN_DONE, 'lan', ConnectionColor::Primary));
        $this->insertRunState(self::CONN_DONE, 'done');

        self::assertSame([], $this->pendingRuns->pending());
    }

    private function insertRunState(string $connectionId, string $phase): void
    {
        $this->dbal->insert('run_states', [
            'connection_id' => $connectionId,
            'phase' => $phase,
            'updated_at' => '2026-06-08 10:00:00',
        ]);
    }

    private function connection(string $id, string $name, ConnectionColor $color): Connection
    {
        return new Connection(
            new ConnectionId($id),
            new ProbeId(self::PROBE),
            $name,
            'Orange',
            new ExpectedSpeed(300_000_000, 50_000_000),
            $color,
            Labels::fromArray([]),
            ServerPool::fromArray(['frankfurt.example.net:8080']),
            Schedule::even(24, 0),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
