<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Doctrine;

use App\Dashboard\Application\ReadModel\ProbeLiveness;
use App\Dashboard\Application\ReadModel\ProbeLivenessRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function intdiv;
use function max;

#[AsAlias(id: ProbeLivenessRepository::class, public: true)]
final readonly class SqlProbeLivenessRepository implements ProbeLivenessRepository
{
    private const int ONLINE_WINDOW_SECONDS = 300;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function all(): array
    {
        /** @var list<array{id: string, name: string, last_poll_at: string|null}> $rows */
        $rows = $this->connection->createQueryBuilder()
            ->select("probe.id", "probe.name", "probe.last_poll_at")
            ->from("probes", "probe")
            ->orderBy("probe.created_at", "DESC")
            ->executeQuery()
            ->fetchAllAssociative();

        $nowUnix = $this->clock->now()->getTimestamp();

        $liveness = [];

        foreach ($rows as $row) {
            $lastPollUnix = $row["last_poll_at"] === null
                ? null
                : new DateTimeImmutable($row["last_poll_at"], new DateTimeZone("UTC"))->getTimestamp();

            $secondsSince = $lastPollUnix === null ? null : max(0, $nowUnix - $lastPollUnix);

            $liveness[] = new ProbeLiveness(
                $row["id"],
                $row["name"],
                $secondsSince !== null && $secondsSince <= self::ONLINE_WINDOW_SECONDS,
                $lastPollUnix,
                $secondsSince === null ? null : intdiv($secondsSince, 60),
            );
        }

        return $liveness;
    }
}
