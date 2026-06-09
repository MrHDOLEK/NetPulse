<?php

declare(strict_types=1);

namespace App\Measurement\Application\Command\RecordMeasurement;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Exception\ConnectionNotOwnedByProbe;
use App\Measurement\Application\Ookla\OoklaResultMapper;
use App\Measurement\Domain\Event\MeasurementRecorded;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\Service\HealthEvaluator;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Shared\Application\Service\IdGeneratorInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: "command.bus")]
final readonly class RecordMeasurementHandler
{
    public function __construct(
        private MeasurementRepository $measurements,
        private ConnectionRepository $connections,
        private IdGeneratorInterface $idGenerator,
        #[Autowire(service: "event.bus")]
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
        private OoklaResultMapper $mapper,
        private HealthEvaluator $healthEvaluator,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RecordMeasurementCommand $command): void
    {
        $connection = $this->connections->find($command->connectionId);

        if ($connection === null || !$connection->belongsTo($command->probeId)) {
            throw new ConnectionNotOwnedByProbe();
        }

        $measurementId = new MeasurementId($this->idGenerator->generate()->toString());
        $now = $this->clock->now();

        $measurement = $this->mapper->toMeasurement(
            $measurementId,
            $command->probeId,
            $command->connectionId,
            $command->ookla,
            $command->scheduled,
            $now,
            $command->rawPayload,
        );

        $verdict = $this->healthEvaluator->evaluate(
            $measurement,
            $connection->thresholds(),
            $connection->expected(),
        );
        $measurement->markHealth($verdict->isHealthy());

        $this->measurements->save($measurement);

        $this->logger->info("measurement recorded", [
            "probe" => $command->probeId->toString(),
            "connection" => $command->connectionId->toString(),
            "status" => $measurement->status()->value,
            "healthy" => $verdict->isHealthy(),
        ]);

        $this->eventBus->dispatch(new MeasurementRecorded(
            $measurementId,
            $command->probeId,
            $command->connectionId,
            $now,
        ));
    }
}
