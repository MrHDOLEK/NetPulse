<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Symfony\EventListener;

use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Scheduling\Domain\RunStateRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: "event.bus")]
final readonly class MarkRunDoneOnMeasurementRecorded
{
    public function __construct(
        private RunStateRepository $runStates,
    ) {}

    public function __invoke(MeasurementRecorded $event): void
    {
        $this->runStates->markDoneIfPending($event->connectionId, $event->occurredAt);
    }
}
