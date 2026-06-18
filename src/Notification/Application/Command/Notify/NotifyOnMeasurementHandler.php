<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\Notify;

use App\Connection\Domain\ConnectionRepository;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\MeasurementRepository;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Notification\Application\NotificationHealthRepository;
use App\Notification\Application\NotificationRenderer;
use App\Notification\Application\NotificationSettingsProvider;
use App\Notification\Domain\Enum\NotificationKind;
use App\Notification\Domain\Enum\NotificationSeverity;
use App\Notification\Domain\NotificationDispatcher;
use App\Notification\Domain\Service\AlertDecider;
use App\Notification\Domain\ValueObject\AlertDecision;
use App\Probe\Domain\ProbeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NotifyOnMeasurementHandler
{
    public function __construct(
        private MeasurementRepository $measurements,
        private ConnectionRepository $connections,
        private ProbeRepository $probes,
        private NotificationHealthRepository $health,
        private AlertDecider $alertDecider,
        private NotificationRenderer $renderer,
        private NotificationDispatcher $dispatcher,
        private NotificationSettingsProvider $settingsProvider,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(NotifyOnMeasurementCommand $command): void
    {
        $measurement = $this->measurements->get(new MeasurementId($command->measurementId));
        $connectionId = $measurement->connectionId();

        $threshold = $this->settingsProvider->current()->consecutiveThreshold;

        $history = $this->health->forConnection($connectionId, $threshold + 1);
        $decision = $this->alertDecider->decide($history, $threshold);

        if (!$decision->shouldNotify()) {
            return;
        }

        $connection = $this->connections->get($connectionId);
        $probe = $this->probes->get($measurement->probeId());

        $notification = $this->renderer->render(
            $this->kind($decision),
            $this->severity($decision),
            $this->context($decision, $connection->name(), $probe->name(), $measurement),
        );

        $this->dispatcher->send($notification);

        $this->logger->info('notification dispatched', [
            'kind' => $notification->kind->value,
            'connection' => $connection->name(),
        ]);
    }

    private function kind(AlertDecision $decision): NotificationKind
    {
        return $decision->kind() ?? NotificationKind::Alert;
    }

    private function severity(AlertDecision $decision): NotificationSeverity
    {
        return $this->kind($decision) === NotificationKind::Alert
            ? NotificationSeverity::Critical
            : NotificationSeverity::Info;
    }

    /**
     * @return array<string, scalar>
     */
    private function context(
        AlertDecision $decision,
        string $connectionName,
        string $probeName,
        Measurement $measurement,
    ): array {
        $bandwidth = $measurement->bandwidth();
        $latency = $measurement->latency();
        $packetLoss = $measurement->packetLoss();

        return [
            'probe' => $probeName,
            'connection' => $connectionName,
            'reason' => $decision->reason,

            'downloadBits' => $bandwidth === null ? 0 : $bandwidth->downloadBits,
            'uploadBits' => $bandwidth === null ? 0 : $bandwidth->uploadBits,
            'pingMs' => $latency === null ? 0.0 : $latency->ping,
            'packetLoss' => $packetLoss === null ? 0.0 : $packetLoss->ratio * 100.0,
        ];
    }
}
