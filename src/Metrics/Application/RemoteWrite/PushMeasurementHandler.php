<?php

declare(strict_types=1);

namespace App\Metrics\Application\RemoteWrite;

use App\Connection\Domain\ConnectionRepository;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Metrics\Domain\RemoteWrite\Exception\RemoteWriteFailed;
use App\Metrics\Domain\RemoteWrite\RemoteWriteClient;
use App\Metrics\Domain\RemoteWriteFailureCounter;
use App\Probe\Domain\ProbeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PushMeasurementHandler
{
    public function __construct(
        private MeasurementRepository $measurements,
        private ConnectionRepository $connections,
        private ProbeRepository $probes,
        private MeasurementTimeSeriesMapper $mapper,
        private RemoteWriteClient $client,
        private RemoteWriteFailureCounter $failures,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PushMeasurementMessage $message): void
    {
        $measurement = $this->measurements->get(new MeasurementId($message->measurementId));

        $connection = $this->connections->get($measurement->connectionId());
        $probe = $this->probes->get($measurement->probeId());

        $series = $this->mapper->map($measurement, $connection, $probe);

        try {
            $this->client->write($series);

            $this->logger->debug("remote write pushed", [
                "connection" => $connection->name(),
                "measurement" => $message->measurementId,
            ]);
        } catch (RemoteWriteFailed $exception) {
            $this->failures->increment();
            $this->logger->error("remote write push failed", [
                "connection" => $connection->name(),
                "measurement" => $message->measurementId,
                "error" => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
