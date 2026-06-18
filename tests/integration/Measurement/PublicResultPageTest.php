<?php

declare(strict_types=1);

namespace App\Tests\Integration\Measurement;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicResultPageTest extends KernelTestCase
{
    private const string PROBE = '11111111-1111-1111-1111-111111111111';
    private const string CONN = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CONN_NAME = 'Secret Internal WAN Name';
    private const string MEASUREMENT_ID = 'eeeeeeee-0000-0000-0000-000000000042';
    private const string SHARE_TOKEN = 'abcdefghijklmnopqrstuvwxyz0123456789_-ABCDE';
    private const string UNKNOWN_TOKEN = 'ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ';

    private DbalConnection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get('doctrine.dbal.default_connection');
    }

    public function testPublicPageRendersSafeFieldsWithoutLoginAndLeaksNothingInternal(): void
    {
        $this->seed();

        $response = $this->get('/r/' . self::SHARE_TOKEN);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();

        self::assertStringContainsString('Download', $body);
        self::assertStringContainsString('Mbps', $body);
        self::assertStringContainsString('Acme Speedtest', $body);
        self::assertStringContainsString('Acme ISP', $body);

        self::assertStringNotContainsString(self::CONN_NAME, $body, 'public page leaked the connection name');
        self::assertStringNotContainsString('rawPayload', $body, 'public page leaked the raw payload key');
        self::assertStringNotContainsString(self::MEASUREMENT_ID, $body, 'public page leaked the measurement uuid');
        self::assertStringNotContainsString('speedtest.acme.example', $body, 'public page leaked the server host');
    }

    public function testUnknownTokenReturns404(): void
    {
        $this->seed();

        $response = $this->get('/r/' . self::UNKNOWN_TOKEN);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShortTokenReturns404(): void
    {
        $this->seed();

        $response = $this->get('/r/tooshort');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function get(string $path): Response
    {
        $request = Request::create($path, 'GET');

        return self::getContainer()->get('kernel')->handle($request);
    }

    private function seed(): void
    {
        $this->db->insert('probes', [
            'id' => self::PROBE,
            'name' => 'home',
            'labels' => json_encode([], JSON_THROW_ON_ERROR),
            'token_hash' => 'x',
            'enabled' => 1,
            'created_at' => '2026-06-05 10:00:00',
        ]);

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

        $this->db->insert(
            'measurements',
            [
                'id' => self::MEASUREMENT_ID,
                'probe_id' => self::PROBE,
                'connection_id' => self::CONN,
                'status' => 'completed',
                'scheduled' => 1,
                'started_at' => '2026-06-05 11:59:55',
                'completed_at' => '2026-06-05 12:00:00',
                'server_id' => '100',
                'server_name' => 'Acme Speedtest',
                'server_location' => 'Warsaw',
                'server_host' => 'speedtest.acme.example:8080',
                'isp' => 'Acme ISP',
                'download_bits' => 900_000_000,
                'upload_bits' => 90_000_000,
                'ping' => 50.0,
                'jitter' => 10.0,
                'packet_loss_ratio' => 0.02,
                'data_used_download' => 123_456,
                'data_used_upload' => 7_890,
                'download_elapsed' => 4000,
                'upload_elapsed' => 4000,
                'raw_payload' => json_encode(['type' => 'result'], JSON_THROW_ON_ERROR),
                'healthy' => true,
                'share_token' => self::SHARE_TOKEN,
            ],
            [
                'healthy' => Types::BOOLEAN,
            ],
        );
    }
}
