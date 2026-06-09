<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Symfony\EventListener;

use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Notification\Application\Command\Notify\NotifyOnMeasurementCommand;
use App\Notification\Application\NotificationSettingsProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: "event.bus")]
final readonly class NotifyOnMeasurementRecorded
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private NotificationSettingsProvider $settingsProvider,
    ) {}

    public function __invoke(MeasurementRecorded $event): void
    {
        if (!$this->settingsProvider->current()->enabled) {
            return;
        }

        $this->commandBus->dispatch(
            new NotifyOnMeasurementCommand($event->measurementId->toString()),
        );
    }
}
