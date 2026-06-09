<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\EventListener;

use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Metrics\Application\RemoteWrite\PushMeasurementMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: "event.bus")]
final readonly class PushMeasurementOnRecorded
{
    public function __construct(
        private MessageBusInterface $commandBus,
        #[Autowire("%netpulse.remote_write.enabled%")]
        private bool $enabled,
    ) {}

    public function __invoke(MeasurementRecorded $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->commandBus->dispatch(
            new PushMeasurementMessage($event->measurementId->toString()),
        );
    }
}
