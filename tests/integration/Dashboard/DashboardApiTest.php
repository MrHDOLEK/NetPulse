<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function is_array;
use function sprintf;

final class DashboardApiTest extends KernelTestCase
{
    private const string CSRF_TOKEN_ID = 'authenticate';
    private const string RUN_TEST_TOKEN_ID = 'run-test';
    private const string CSRF_RAW_TOKEN = 'phpunit-login-token';
    private const string ADMIN_EMAIL = 'admin@example.com';
    private const string ADMIN_PASSWORD = 'correct-horse-battery';
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CONN_NAME = 'Fibre WAN Primary';

    private DbalConnection $db;
    private MessageBusInterface $commandBus;
    private Session $session;
    private int $measurementSeq = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->commandBus = $container->get(MessageBusInterface::class);

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
    }

    public function testSeriesReturnsSpeedJson(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=7d&metric=speed', self::CONN));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $payload = $this->decode($response);

        self::assertSame(self::CONN, $payload['connectionId']);
        self::assertSame('7d', $payload['range']);
        self::assertSame('speed', $payload['metric']);
        self::assertArrayHasKey('trendPct', $payload);
        self::assertIsArray($payload['buckets']);

        $populated = $this->firstPopulated($payload['buckets'], 'dl');
        self::assertNotNull($populated, 'expected at least one bucket with a download value');
        self::assertArrayHasKey('t', $populated);
        self::assertArrayHasKey('dl', $populated);
        self::assertArrayHasKey('up', $populated);
        self::assertIsInt($populated['t']);

        self::assertGreaterThan(100_000_000, $populated['dl']);
    }

    public function testSeriesReturnsPingJson(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=7d&metric=ping', self::CONN));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertSame('ping', $payload['metric']);

        $populated = $this->firstPopulated($payload['buckets'], 'ping');
        self::assertNotNull($populated);
        self::assertArrayHasKey('t', $populated);
        self::assertArrayHasKey('ping', $populated);
        self::assertArrayNotHasKey('dl', $populated);

        self::assertLessThan(1.0, $populated['ping']);
    }

    public function testSeriesReturnsLossJson(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=7d&metric=loss', self::CONN));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertSame('loss', $payload['metric']);

        $populated = $this->firstPopulated($payload['buckets'], 'loss');
        self::assertNotNull($populated);
        self::assertArrayHasKey('t', $populated);
        self::assertArrayHasKey('loss', $populated);
        self::assertArrayNotHasKey('dl', $populated);
    }

    public function testSeriesRejectsBadRange(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=5y&metric=speed', self::CONN));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testSeriesRejectsBadMetric(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=7d&metric=foo', self::CONN));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testSeriesRejectsInvalidConnectionUuid(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get('/dashboard/series?connection=not-a-uuid&range=7d&metric=speed');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testSeriesRejectsMissingConnection(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get('/dashboard/series?range=7d&metric=speed');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testSeriesRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get(sprintf('/dashboard/series?connection=%s&range=7d&metric=speed', self::CONN));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function testSnapshotReturnsJson(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get('/dashboard/snapshot');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $payload = $this->decode($response);

        self::assertArrayHasKey('connections', $payload);
        self::assertArrayHasKey('prometheus', $payload);
        self::assertSame(['status' => 'scraping', 'endpoint' => '/metrics'], $payload['prometheus']);

        self::assertIsArray($payload['connections']);
        self::assertCount(1, $payload['connections']);

        $connection = $payload['connections'][0];
        self::assertSame(self::CONN, $connection['connectionId']);
        self::assertSame(self::CONN_NAME, $connection['name']);

        foreach ([
            'status',
            'downloadBits',
            'uploadBits',
            'pingSeconds',
            'packetLossRatio',
            'uptimePct',
            'latestHealthy',
            'completedAtUnix',
        ] as $field) {
            self::assertArrayHasKey($field, $connection);
        }

        self::assertSame(955_000_000, $connection['downloadBits']);
        self::assertLessThan(1.0, $connection['pingSeconds']);
    }

    public function testSnapshotRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get('/dashboard/snapshot');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function testCursorReturnsChangeTokenJson(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->get('/dashboard/cursor');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $payload = $this->decode($response);

        self::assertArrayHasKey('latestCompletedAtUnix', $payload);
        self::assertArrayHasKey('totalMeasurementCount', $payload);

        self::assertIsInt($payload['latestCompletedAtUnix']);
        self::assertSame(3, $payload['totalMeasurementCount']);
    }

    public function testCursorRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->get('/dashboard/cursor');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function testRunQueuesConnectionScopeWithPinAndMarksDueNow(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun([
            'scope' => 'connection',
            'connectionId' => self::CONN,
            'serverId' => '12345',
        ], $this->validRunTestToken());

        self::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_ACCEPTED]);
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $payload = $this->decode($response);
        self::assertSame('queued', $payload['status']);

        self::assertSame(1, $this->markerCount(self::CONN), 'expected a due-now marker for the connection');
        self::assertSame('12345', $this->markerForcedServerId(self::CONN));
    }

    public function testRunQueuesAllScope(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun(['scope' => 'all'], $this->validRunTestToken());

        self::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_ACCEPTED]);
        $payload = $this->decode($response);
        self::assertSame('queued', $payload['status']);

        self::assertSame(1, $this->markerCount(self::CONN));
        self::assertNull($this->markerForcedServerId(self::CONN));
    }

    public function testRunRejectsAllScopeWithServerId(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun(['scope' => 'all', 'serverId' => '12345'], $this->validRunTestToken());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(0, $this->markerCount(self::CONN), 'no marker may be created for an invalid all+pin request');
    }

    public function testRunRejectsUnknownScope(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun(['scope' => 'nonsense', 'connectionId' => self::CONN], $this->validRunTestToken());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRunRejectsMissingCsrfToken(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun(['scope' => 'connection', 'connectionId' => self::CONN], null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(0, $this->markerCount(self::CONN), 'no marker may be created without a valid CSRF token');
    }

    public function testRunRejectsInvalidCsrfToken(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun(['scope' => 'connection', 'connectionId' => self::CONN], 'not-the-real-token');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(0, $this->markerCount(self::CONN), 'no marker may be created with an invalid CSRF token');
    }

    public function testRunRejectsNonUuidConnectionWithBadRequest(): void
    {
        $this->seedWorld();
        $this->login();

        $response = $this->postRun([
            'scope' => 'connection',
            'connectionId' => 'not-a-uuid',
        ], $this->validRunTestToken());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRunReturnsNotFoundForUnknownConnection(): void
    {
        $this->seedWorld();
        $this->login();

        $unknown = 'bbbbbbbb-0000-0000-0000-000000000099';
        $response = $this->postRun(['scope' => 'connection', 'connectionId' => $unknown], $this->validRunTestToken());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(0, $this->markerCount($unknown), 'no marker may be created for an unknown connection');
    }

    public function testRunRequiresAuthentication(): void
    {
        $this->seedWorld();

        $response = $this->postRun(['scope' => 'connection', 'connectionId' => self::CONN], $this->validRunTestToken());

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    /**
     * @param list<array<string, mixed>> $buckets
     *
     * @return array<string, mixed>|null
     */
    private function firstPopulated(array $buckets, string $key): ?array
    {
        foreach ($buckets as $bucket) {
            if (is_array($bucket) && ($bucket[$key] ?? null) !== null) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function seedWorld(): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand(self::ADMIN_EMAIL, self::ADMIN_PASSWORD));
        $this->insertProbe();
        $this->insertConnection();
        $this->seedMeasurements();
    }

    private function login(): void
    {
        $this->session->set(SessionTokenStorage::SESSION_NAMESPACE . '/' . self::CSRF_TOKEN_ID, self::CSRF_RAW_TOKEN);

        $request = Request::create('/login', 'POST', [
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
            '_csrf_token' => self::CSRF_RAW_TOKEN,
        ]);
        $this->attachSession($request);

        self::getContainer()->get('kernel')->handle($request);
    }

    private function get(string $path): Response
    {
        $request = Request::create($path, 'GET');
        $this->attachSession($request);

        return self::getContainer()->get('kernel')->handle($request);
    }

    private function validRunTestToken(): string
    {
        $token = 'phpunit-run-test-token';
        $this->session->set(SessionTokenStorage::SESSION_NAMESPACE . '/' . self::RUN_TEST_TOKEN_ID, $token);

        return $token;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postRun(array $body, ?string $csrfToken): Response
    {
        $request = Request::create(
            '/dashboard/run',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );

        if ($csrfToken !== null) {
            $request->headers->set('X-CSRF-Token', $csrfToken);
        }
        $this->attachSession($request);

        return self::getContainer()->get('kernel')->handle($request);
    }

    private function markerCount(string $connectionId): int
    {
        $count = $this->db->fetchOne('SELECT COUNT(*) FROM due_now_markers WHERE connection_id = :id', [
            'id' => $connectionId,
        ]);

        return (int) $count;
    }

    private function markerForcedServerId(string $connectionId): ?string
    {
        $value = $this->db->fetchOne('SELECT forced_server_id FROM due_now_markers WHERE connection_id = :id', [
            'id' => $connectionId,
        ]);

        return $value === false ? null : ($value === null ? null : (string) $value);
    }

    private function attachSession(Request $request): void
    {
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), $this->session->getId());
    }

    private function seedMeasurements(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'))->getTimestamp();

        $this->insertMeasurement('completed', $now - (3 * 3600), 920_000_000, 92_000_000, 12.0, 1.5, 0.0, true);
        $this->insertMeasurement('completed', $now - (2 * 3600), 940_000_000, 95_000_000, 11.0, 1.2, 0.0, true);
        $this->insertMeasurement('completed', $now - (1 * 3600), 955_000_000, 98_000_000, 9.5, 1.0, 0.0, true);
    }

    private function insertProbe(): void
    {
        $this->db->insert('probes', [
            'id' => self::PROBE,
            'name' => 'home',
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);
    }

    private function insertConnection(): void
    {
        $this->db->insert('connections', [
            'id' => self::CONN,
            'probe_id' => self::PROBE,
            'name' => self::CONN_NAME,
            'isp' => 'Acme ISP',
            'expected_download_bits' => 1_000_000_000,
            'expected_upload_bits' => 500_000_000,
            'color' => 'primary',
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

    private function insertMeasurement(
        string $status,
        int $completedAtUnix,
        ?int $downloadBits,
        ?int $uploadBits,
        ?float $pingMs,
        ?float $jitterMs,
        ?float $packetLossRatio,
        ?bool $healthy,
    ): void {
        $completedAt = new DateTimeImmutable('@' . $completedAtUnix)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $sequence = ++$this->measurementSeq;

        $this->db->insert(
            'measurements',
            [
                'id' => sprintf('eeeeeeee-0000-0000-0000-%012d', $sequence),
                'probe_id' => self::PROBE,
                'connection_id' => self::CONN,
                'status' => $status,
                'scheduled' => 1,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'server_id' => '12345',
                'server_name' => 'Acme Speedtest',
                'server_location' => 'Warsaw',
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
    }
}
