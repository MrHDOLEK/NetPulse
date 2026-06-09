<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Cron;

use App\Scheduling\Domain\CronEvaluator;
use Cron\CronExpression;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CronEvaluator::class)]
final readonly class DragonmantankCronEvaluator implements CronEvaluator
{
    public function matchesSince(string $expression, DateTimeImmutable $since, DateTimeImmutable $now): bool
    {
        $previousRun = (new CronExpression($expression))->getPreviousRunDate($now, 0, true);

        return $previousRun->getTimestamp() > $since->getTimestamp();
    }

    public function isValid(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }
}
