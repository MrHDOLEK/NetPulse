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
use RuntimeException;

use function sprintf;

final class IngestFixturesContext implements Context
{
    public const PROBE_ID = "22222222-2222-4222-8222-222222222222";
    public const CONNECTION_ID = "33333333-3333-4333-8333-333333333333";
    public const PROBE_TOKEN = "secret-token";

    public function __construct(
        private readonly ProbeRepository $probes,
        private readonly ConnectionRepository $connections,
        private readonly ProbeTokenHasher $tokenHasher,
        private readonly DbalConnection $db,
    ) {}

    /**
     * @Given an enabled probe with a connection exists
     */
    public function anEnabledProbeWithAConnectionExists(): void
    {
        $this->seedProbe(true);
        $this->seedConnection(new ProbeId(self::PROBE_ID));
    }

    /**
     * @Given a disabled probe exists
     */
    public function aDisabledProbeExists(): void
    {
        $this->seedProbe(false);
        $this->seedConnection(new ProbeId(self::PROBE_ID));
    }

    /**
     * @Given a probe exists with a connection owned by another probe
     */
    public function aProbeExistsWithAConnectionOwnedByAnotherProbe(): void
    {
        $this->seedProbe(true);
        $this->seedConnection(new ProbeId("44444444-4444-4444-8444-444444444444"));
    }

    /**
     * @Then the latest measurement on the connection is recorded as unhealthy
     */
    public function theLatestMeasurementIsUnhealthy(): void
    {
        $this->assertLatestHealthy(false);
    }

    /**
     * @Then the latest measurement on the connection is recorded as healthy
     */
    public function theLatestMeasurementIsHealthy(): void
    {
        $this->assertLatestHealthy(true);
    }

    private function assertLatestHealthy(bool $expected): void
    {
        $healthy = $this->db->fetchOne(
            "SELECT healthy FROM measurements WHERE connection_id = ? ORDER BY completed_at DESC, id DESC LIMIT 1",
            [self::CONNECTION_ID],
        );

        if ($healthy === false) {
            throw new RuntimeException("No measurement was recorded for the connection.");
        }

        $actual = (bool)$healthy;

        if ($actual !== $expected) {
            throw new RuntimeException(sprintf(
                "Expected latest measurement healthy=%s, got healthy=%s.",
                $expected ? "true" : "false",
                $actual ? "true" : "false",
            ));
        }
    }

    private function seedProbe(bool $enabled): void
    {
        $this->probes->save(new Probe(
            new ProbeId(self::PROBE_ID),
            "home",
            Labels::fromArray(["site" => "home", "link" => "wan1"]),
            $this->tokenHasher->hash(self::PROBE_TOKEN),
            $enabled,
            new DateTimeImmutable(),
        ));
    }

    private function seedConnection(ProbeId $owner): void
    {
        $this->connections->save(new Connection(
            new ConnectionId(self::CONNECTION_ID),
            $owner,
            "wan1",
            "Orange Polska",
            new ExpectedSpeed(1_000_000_000, 100_000_000),
            ConnectionColor::Primary,
            Labels::fromArray(["link" => "wan1"]),
            ServerPool::fromList("12746"),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        ));
    }
}
