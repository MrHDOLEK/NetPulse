<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain;

use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    private const string CONNECTION_UUID = "11111111-1111-7111-8111-111111111111";
    private const string PROBE_UUID = "22222222-2222-7222-8222-222222222222";

    public function testExposesItsAttributes(): void
    {
        $connection = $this->connection();

        $this->assertSame(self::CONNECTION_UUID, $connection->id()->toString());
        $this->assertSame(self::PROBE_UUID, $connection->probeId()->toString());
        $this->assertSame("Home WAN1", $connection->name());
        $this->assertSame("Orange", $connection->isp());
        $this->assertSame(300_000_000, $connection->expected()->expectedDownloadBits);
        $this->assertSame(50_000_000, $connection->expected()->expectedUploadBits);
        $this->assertSame(ConnectionColor::Violet, $connection->color());
        $this->assertSame(["site" => "home", "link" => "wan1"], $connection->labels()->all());
        $this->assertSame("home", $connection->labels()->get("site"));
        $this->assertSame(["frankfurt.example.net:8080", "warsaw.example.net:8080"], $connection->serverPool()->all());
        $this->assertTrue($connection->isEnabled());
    }

    public function testEnableAndDisableToggleTheFlag(): void
    {
        $connection = $this->connection();

        $connection->disable();
        $this->assertFalse($connection->isEnabled());

        $connection->enable();
        $this->assertTrue($connection->isEnabled());
    }

    public function testBelongsToMatchingProbe(): void
    {
        $connection = $this->connection();

        $this->assertTrue($connection->belongsTo(new ProbeId(self::PROBE_UUID)));
        $this->assertFalse($connection->belongsTo(new ProbeId("33333333-3333-7333-8333-333333333333")));
    }

    public function testReconfigureReplacesEditableFieldsAndLeavesIdentityUntouched(): void
    {
        $connection = $this->connection();

        $connection->reconfigure(
            "Renamed WAN",
            "Vodafone",
            new ExpectedSpeed(900_000_000, 90_000_000),
            ConnectionColor::Amber,
            Labels::fromArray(["env" => "prod"]),
            ServerPool::fromArray(["paris.example.net:8080"]),
            Schedule::cron("*/15 * * * *"),
            Thresholds::of(0.9, 0.5, null, null, null),
            AdaptivePolicy::of(60, 1, 2),
        );

        $this->assertSame("Renamed WAN", $connection->name());
        $this->assertSame("Vodafone", $connection->isp());
        $this->assertSame(900_000_000, $connection->expected()->expectedDownloadBits);
        $this->assertSame(90_000_000, $connection->expected()->expectedUploadBits);
        $this->assertSame(ConnectionColor::Amber, $connection->color());
        $this->assertSame(["env" => "prod"], $connection->labels()->all());
        $this->assertSame(["paris.example.net:8080"], $connection->serverPool()->all());
        $this->assertSame(["*/15 * * * *"], $connection->schedule()->cronExpressions());
        $this->assertSame(0.9, $connection->thresholds()->minDownloadRatio());
        $this->assertNull($connection->thresholds()->maxPingMs());
        $this->assertSame(60, $connection->adaptivePolicy()->adaptiveIntervalSeconds());

        $this->assertSame(self::CONNECTION_UUID, $connection->id()->toString());
        $this->assertSame(self::PROBE_UUID, $connection->probeId()->toString());
        $this->assertTrue($connection->isEnabled());
    }

    public function testReconfigureLeavesADisabledConnectionDisabled(): void
    {
        $connection = $this->connection();
        $connection->disable();

        $connection->reconfigure(
            "Still Disabled",
            "Orange",
            new ExpectedSpeed(0, 0),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::empty(),
            Schedule::even(12, 30),
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        $this->assertFalse($connection->isEnabled());
    }

    public function testServerPoolKeysAreReindexedToAList(): void
    {
        $connection = new Connection(
            new ConnectionId(self::CONNECTION_UUID),
            new ProbeId(self::PROBE_UUID),
            "Home WAN1",
            "Orange",
            new ExpectedSpeed(0, 0),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::fromArray([3 => "a.example.net", 7 => "b.example.net"]),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );

        $this->assertSame(["a.example.net", "b.example.net"], $connection->serverPool()->all());
    }

    private function connection(): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION_UUID),
            new ProbeId(self::PROBE_UUID),
            "Home WAN1",
            "Orange",
            new ExpectedSpeed(300_000_000, 50_000_000),
            ConnectionColor::Violet,
            Labels::fromArray(["site" => "home", "link" => "wan1"]),
            ServerPool::fromArray(["frankfurt.example.net:8080", "warsaw.example.net:8080"]),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }
}
