<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\GenerateDigest;

use App\Notification\Application\Digest\DigestRepository;
use App\Notification\Application\NotificationRenderer;
use App\Notification\Domain\NotificationDispatcher;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class GenerateDigestHandler
{
    public function __construct(
        private DigestRepository $digests,
        private NotificationRenderer $renderer,
        private NotificationDispatcher $dispatcher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateDigestCommand $command): void
    {
        $since = $this->since($command->period);
        $digests = $this->digests->since($since);

        if ($digests->isEmpty()) {
            $this->logger->info('digest skipped: no data', [
                'period' => $command->period,
            ]);

            return;
        }

        $notification = $this->renderer->renderDigest($command->period, $digests);

        $this->dispatcher->send($notification);

        $this->logger->info('digest sent', [
            'period' => $command->period,
            'connections' => $digests->count(),
        ]);
    }

    private function since(string $period): DateTimeImmutable
    {
        $now = $this->clock->now();

        return match ($period) {
            GenerateDigestPeriod::Weekly->value => $now->modify('-7 days'),
            default => $now->modify('-1 day'),
        };
    }
}
