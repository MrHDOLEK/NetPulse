<?php

declare(strict_types=1);

namespace App\Probe\Infrastructure\Doctrine;

use App\Probe\Domain\ProbePollRecorder;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ProbePollRecorder::class)]
final readonly class DoctrineProbePollRecorder implements ProbePollRecorder
{
    private const int DEBOUNCE_SECONDS = 30;

    public function __construct(
        private Connection $connection,
    ) {}

    public function recordPoll(ProbeId $probeId, DateTimeImmutable $now): void
    {
        $cutoff = $now->modify('-' . self::DEBOUNCE_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        $this->connection->executeStatement('UPDATE probes SET last_poll_at = :now '
        . 'WHERE id = :probeId AND (last_poll_at IS NULL OR last_poll_at <= :cutoff)', [
            'now' => $now->format('Y-m-d H:i:s'),
            'probeId' => $probeId->toString(),
            'cutoff' => $cutoff,
        ]);
    }
}
