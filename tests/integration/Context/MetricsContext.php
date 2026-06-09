<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class MetricsContext implements Context
{
    /** @var array<string, string> */
    private array $probeIds = [];

    /** @var array<string, string> */
    private array $connectionIds = [];

    private Response $response;

    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
    ) {}

    /**
     * @Given a probe :name with site :site exists
     */
    public function aProbeWithSiteExists(string $name, string $site): void
    {
        $id = $this->uuid();
        $this->probeIds[$name] = $id;

        $this->connection->insert("probes", [
            "id" => $id,
            "name" => $name,
            "labels" => json_encode(["site" => $site], JSON_THROW_ON_ERROR),
            "token_hash" => "x",
            "enabled" => 1,
            "created_at" => gmdate("Y-m-d H:i:s"),
        ]);
    }

    /**
     * @Given a connection :name on probe :probe with isp :isp expecting :down down and :up up
     */
    public function aConnectionOnProbeWithIspExpecting(
        string $name,
        string $probe,
        string $isp,
        int $down,
        int $up,
    ): void {
        $id = $this->uuid();
        $this->connectionIds[$name] = $id;

        $this->connection->insert("connections", [
            "id" => $id,
            "probe_id" => $this->probeIds[$probe],
            "name" => $name,
            "isp" => $isp,
            "expected_download_bits" => $down,
            "expected_upload_bits" => $up,
            "color" => "primary",
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "server_pool" => json_encode([], JSON_THROW_ON_ERROR),
            "schedule" => json_encode(["mode" => "even", "cronExpressions" => [], "testsPerDay" => 24, "jitterSeconds" => 120], JSON_THROW_ON_ERROR),
            "thresholds" => json_encode(["minDownloadRatio" => 0.7, "minUploadRatio" => 0.7, "maxPingMs" => 100, "maxJitterMs" => 50, "maxPacketLossRatio" => 0.05], JSON_THROW_ON_ERROR),
            "adaptive_policy" => json_encode(["adaptiveIntervalSeconds" => 300, "recoveryHealthyCount" => 3, "maxConsecutiveFailures" => 5], JSON_THROW_ON_ERROR),
            "enabled" => 1,
        ]);
    }

    /**
     * @Given a completed measurement on connection :connection was recorded :ago seconds ago with download :download and ping :ping ms
     */
    public function aCompletedMeasurementWasRecorded(
        string $connection,
        int $ago,
        int $download,
        int $ping,
    ): void {
        $this->insertCompleted($connection, $ago, $download, $ping);
    }

    /**
     * @Given a stale completed measurement on connection :connection was recorded :ago seconds ago with download :download and ping :ping ms
     */
    public function aStaleCompletedMeasurementWasRecorded(
        string $connection,
        int $ago,
        int $download,
        int $ping,
    ): void {
        $this->insertCompleted($connection, $ago, $download, $ping);
    }

    /**
     * @Given an unhealthy measurement on connection :connection was recorded :ago seconds ago with download :download and ping :ping ms
     */
    public function anUnhealthyMeasurementWasRecorded(
        string $connection,
        int $ago,
        int $download,
        int $ping,
    ): void {
        $this->insertCompleted($connection, $ago, $download, $ping, false);
    }

    /**
     * @Given a failed measurement on connection :connection was recorded :ago seconds ago
     */
    public function aFailedMeasurementWasRecorded(string $connection, int $ago): void
    {
        $connectionId = $this->connectionIds[$connection];
        $probeId = $this->probeIdForConnection($connection);
        $completedAt = gmdate("Y-m-d H:i:s", time() - $ago);

        $this->connection->insert("measurements", [
            "id" => $this->uuid(),
            "probe_id" => $probeId,
            "connection_id" => $connectionId,
            "status" => "failed",
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "data_used_download" => 0,
            "data_used_upload" => 0,
            "download_elapsed" => 0,
            "upload_elapsed" => 0,
            "raw_payload" => json_encode([], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @Given a notification send was recorded for kind :kind channel :channel status :status
     */
    public function aNotificationSendWasRecorded(string $kind, string $channel, string $status): void
    {
        $this->connection->executeStatement(
            "INSERT INTO notification_send_counts (kind, channel, status, total) "
            . "VALUES (:kind, :channel, :status, 1) "
            . "ON CONFLICT (kind, channel, status) DO UPDATE SET total = total + 1",
            ["kind" => $kind, "channel" => $channel, "status" => $status],
        );
    }

    /**
     * @When the metrics endpoint is scraped
     */
    public function theMetricsEndpointIsScraped(): void
    {
        $this->response = $this->kernel->handle(Request::create("/metrics", "GET"));
    }

    /**
     * @Then the metrics response code is :code
     */
    public function theMetricsResponseCodeIs(int $code): void
    {
        if ($this->response->getStatusCode() !== $code) {
            throw new RuntimeException(
                sprintf("Metrics response code is %d, %d expected.", $this->response->getStatusCode(), $code),
            );
        }
    }

    /**
     * @Then the metrics response is Prometheus exposition with content type
     */
    public function theMetricsResponseIsPrometheusExpositionWithContentType(): void
    {
        $contentType = (string)$this->response->headers->get("Content-Type");

        if (!str_contains($contentType, "text/plain") || !str_contains($contentType, "version=0.0.4")) {
            throw new RuntimeException(
                sprintf("Expected Prometheus exposition content type, got \"%s\".", $contentType),
            );
        }
    }

    /**
     * @Then the metrics response body contains:
     */
    public function theMetricsResponseBodyContains(PyStringNode $needle): void
    {
        $body = (string)$this->response->getContent();
        $expected = $needle->getRaw();

        if (!str_contains($body, $expected)) {
            throw new RuntimeException(
                sprintf("Metrics response body does not contain \"%s\".", $expected),
            );
        }
    }

    private function insertCompleted(string $connection, int $ago, int $download, int $ping, bool $healthy = true): void
    {
        $connectionId = $this->connectionIds[$connection];
        $probeId = $this->probeIdForConnection($connection);
        $completedAt = gmdate("Y-m-d H:i:s", time() - $ago);

        $this->connection->insert("measurements", [
            "id" => $this->uuid(),
            "probe_id" => $probeId,
            "connection_id" => $connectionId,
            "status" => "completed",
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => "12345",
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Acme ISP",
            "download_bits" => $download,
            "upload_bits" => 480000000,
            "ping" => $ping,
            "jitter" => 2,
            "download_latency_iqm" => 15,
            "upload_latency_iqm" => 18,
            "packet_loss_ratio" => 0,
            "data_used_download" => 100000000,
            "data_used_upload" => 23456789,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "raw_payload" => json_encode([], JSON_THROW_ON_ERROR),
            "healthy" => $healthy,
        ], [
            "healthy" => Types::BOOLEAN,
        ]);
    }

    private function probeIdForConnection(string $connection): string
    {
        /** @var array{probe_id: string} $row */
        $row = $this->connection->fetchAssociative(
            "SELECT probe_id FROM connections WHERE id = ?",
            [$this->connectionIds[$connection]],
        );

        return $row["probe_id"];
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6])&0x0f) | 0x40);
        $data[8] = chr((ord($data[8])&0x3f) | 0x80);

        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
    }
}
