<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe;

use App\Probe\Domain\ProbePollRecorder;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineProbePollRecorderTest extends KernelTestCase
{
    private const string PROBE = "aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa";

    private ProbePollRecorder $recorder;
    private DbalConnection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->recorder = $container->get(ProbePollRecorder::class);
        $this->db = $container->get("doctrine.dbal.default_connection");

        $this->db->executeStatement("DELETE FROM probes WHERE id = :id", ["id" => self::PROBE]);

        $this->db->insert("probes", [
            "id" => self::PROBE,
            "name" => "home",
            "labels" => json_encode([], JSON_THROW_ON_ERROR),
            "token_hash" => "x",
            "enabled" => 1,
            "created_at" => "2026-06-05 10:00:00",
            "last_poll_at" => "2026-06-05 10:00:00",
        ]);
    }

    public function testTwoPollsWithinThirtySecondsCauseOnlyOneWrite(): void
    {
        $base = new DateTimeImmutable("2026-06-06 12:00:00", new DateTimeZone("UTC"));

        $this->recorder->recordPoll(new ProbeId(self::PROBE), $base);
        self::assertSame("2026-06-06 12:00:00", $this->lastPollAt());

        $this->recorder->recordPoll(new ProbeId(self::PROBE), $base->modify("+20 seconds"));
        self::assertSame("2026-06-06 12:00:00", $this->lastPollAt());
    }

    public function testAPollPastThirtySecondsWritesAgain(): void
    {
        $base = new DateTimeImmutable("2026-06-06 12:00:00", new DateTimeZone("UTC"));

        $this->recorder->recordPoll(new ProbeId(self::PROBE), $base);
        self::assertSame("2026-06-06 12:00:00", $this->lastPollAt());

        $this->recorder->recordPoll(new ProbeId(self::PROBE), $base->modify("+31 seconds"));
        self::assertSame("2026-06-06 12:00:31", $this->lastPollAt());
    }

    private function lastPollAt(): ?string
    {
        $value = $this->db->fetchOne(
            "SELECT last_poll_at FROM probes WHERE id = :id",
            ["id" => self::PROBE],
        );

        return $value === false || $value === null ? null : (string)$value;
    }
}
