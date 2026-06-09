<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use Behat\Behat\Context\Context;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;

use function gmdate;
use function json_encode;
use function time;

use const JSON_THROW_ON_ERROR;

final class DueFixturesContext implements Context
{
    public const PROBE_ID = "55555555-5555-4555-8555-555555555555";
    public const PROBE_TOKEN = "due-secret-token";
    public const DUE_CONNECTION_ID = "66666666-6666-4666-8666-666666666666";
    public const RECENT_CONNECTION_ID = "77777777-7777-4777-8777-777777777777";
    public const DEGRADED_CONNECTION_ID = "88888888-8888-4888-8888-888888888888";
    public const HEALTHY_RECENT_CONNECTION_ID = "99999999-9999-4999-8999-999999999999";

    public function __construct(
        private readonly ProbeRepository $probes,
        private readonly ConnectionRepository $connections,
        private readonly ProbeTokenHasher $tokenHasher,
        private readonly DbalConnection $dbal,
    ) {}

    /**
     * @Given an enabled probe with a due connection and a recently-measured connection exists
     */
    public function anEnabledProbeWithADueAndRecentConnectionExists(): void
    {
        $this->seedProbe(true);

        $this->seedConnection(self::DUE_CONNECTION_ID, "wan-due", true);
        $this->seedConnection(self::RECENT_CONNECTION_ID, "wan-recent", true);

        $this->seedMeasurement(self::DUE_CONNECTION_ID, 7200);

        $this->seedMeasurement(self::RECENT_CONNECTION_ID, 60);
    }

    /**
     * @Given a disabled probe with a due connection exists
     */
    public function aDisabledProbeWithADueConnectionExists(): void
    {
        $this->seedProbe(false);
        $this->seedConnection(self::DUE_CONNECTION_ID, "wan-due", true);
        $this->seedMeasurement(self::DUE_CONNECTION_ID, 7200);
    }

    /**
     * @Given an enabled probe with a degraded connection and a healthy recent connection exists
     */
    public function anEnabledProbeWithADegradedAndHealthyRecentConnectionExists(): void
    {
        $this->seedProbe(true);

        $this->seedConnection(self::DEGRADED_CONNECTION_ID, "wan-degraded", true, ServerPool::fromList("srv-a", "srv-b"));
        $this->seedConnection(self::HEALTHY_RECENT_CONNECTION_ID, "wan-healthy", true, ServerPool::fromList("srv-a", "srv-b"));

        $this->seedMeasurement(self::DEGRADED_CONNECTION_ID, 1200, true, "srv-a");
        $this->seedMeasurement(self::DEGRADED_CONNECTION_ID, 600, false, "srv-a");

        $this->seedMeasurement(self::HEALTHY_RECENT_CONNECTION_ID, 600, true, "srv-a");
    }

    private function seedProbe(bool $enabled): void
    {
        $this->probes->save(new Probe(
            new ProbeId(self::PROBE_ID),
            "due-probe",
            Labels::fromArray(["site" => "lab"]),
            $this->tokenHasher->hash(self::PROBE_TOKEN),
            $enabled,
            new DateTimeImmutable(),
        ));
    }

    private function seedConnection(string $connectionId, string $name, bool $enabled, ?ServerPool $pool = null): void
    {
        $connection = new Connection(
            new ConnectionId($connectionId),
            new ProbeId(self::PROBE_ID),
            $name,
            "Orange Polska",
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::fromArray(["link" => $name]),
            $pool ?? ServerPool::fromList("12746"),
            Schedule::even(24, 0),
            $enabled,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        $this->connections->save($connection);
    }

    private function seedMeasurement(
        string $connectionId,
        int $secondsAgo,
        ?bool $healthy = null,
        string $serverId = "12345",
    ): void {
        $completedAt = gmdate("Y-m-d H:i:s", time() - $secondsAgo);

        $this->dbal->insert("measurements", [
            "id" => $this->uuid(),
            "probe_id" => self::PROBE_ID,
            "connection_id" => $connectionId,
            "status" => "completed",
            "scheduled" => 1,
            "started_at" => $completedAt,
            "completed_at" => $completedAt,
            "server_id" => $serverId,
            "server_name" => "Acme Speedtest",
            "server_location" => "Warsaw",
            "server_host" => "speedtest.acme.example:8080",
            "isp" => "Orange Polska",
            "download_bits" => 900000000,
            "upload_bits" => 90000000,
            "ping" => 12,
            "jitter" => 2,
            "download_latency_iqm" => 15,
            "upload_latency_iqm" => 18,
            "packet_loss_ratio" => 0,
            "data_used_download" => 100000000,
            "data_used_upload" => 23456789,
            "download_elapsed" => 4000,
            "upload_elapsed" => 4000,
            "raw_payload" => json_encode([], JSON_THROW_ON_ERROR),
            "healthy" => $healthy === null ? null : ($healthy ? 1 : 0),
        ]);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6])&0x0f) | 0x40);
        $data[8] = chr((ord($data[8])&0x3f) | 0x80);

        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
    }
}
