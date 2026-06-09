<?php

declare(strict_types=1);

namespace App\Probe\Domain;

use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;

interface ProbePollRecorder
{
    public function recordPoll(ProbeId $probeId, DateTimeImmutable $now): void;
}
