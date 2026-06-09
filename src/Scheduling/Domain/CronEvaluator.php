<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use DateTimeImmutable;

interface CronEvaluator
{
    public function matchesSince(string $expression, DateTimeImmutable $since, DateTimeImmutable $now): bool;

    public function isValid(string $expression): bool;
}
