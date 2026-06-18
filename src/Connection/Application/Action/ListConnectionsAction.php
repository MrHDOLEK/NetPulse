<?php

declare(strict_types=1);

namespace App\Connection\Application\Action;

use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ListConnectionsAction extends AbstractController
{
    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly ProbeRepository $probes,
    ) {}

    #[Route('/settings/connections', name: 'settings_connections', methods: ['GET'])]
    public function __invoke(): Response
    {
        $probeNames = [];

        foreach ($this->probes->all() as $probe) {
            $probeNames[$probe->id()->toString()] = $probe->name();
        }

        $connections = [];

        foreach ($this->connections->all() as $connection) {
            $connections[] = $this->toRow($connection, $probeNames);
        }

        $probes = [];

        foreach ($this->probes->all() as $probe) {
            $probes[] = $this->probeRow($probe);
        }

        return $this->render('settings/connections/index.html.twig', [
            'connections' => $connections,
            'probes' => $probes,
        ]);
    }

    /**
     * @param array<string, string> $probeNames
     *
     * @return array<string, mixed>
     */
    private function toRow(Connection $connection, array $probeNames): array
    {
        $schedule = $connection->schedule();
        $probeId = $connection->probeId()->toString();

        return [
            'id' => $connection->id()->toString(),
            'probeId' => $probeId,
            'probeName' => $probeNames[$probeId] ?? null,
            'name' => $connection->name(),
            'isp' => $connection->isp(),
            'color' => $connection->color()->value,
            'enabled' => $connection->isEnabled(),
            'expectedDownloadBits' => $connection->expected()->expectedDownloadBits,
            'expectedUploadBits' => $connection->expected()->expectedUploadBits,
            'labels' => $connection->labels()->all(),
            'serverPool' => $connection->serverPool()->all(),
            'scheduleMode' => $schedule->mode()->value,
            'cronExpressions' => $schedule->cronExpressions(),
            'testsPerDay' => $schedule->testsPerDay(),
            'jitterSeconds' => $schedule->jitterSeconds(),
            'thresholds' => [
                'minDownloadRatio' => $connection->thresholds()->minDownloadRatio(),
                'minUploadRatio' => $connection->thresholds()->minUploadRatio(),
                'maxPingMs' => $connection->thresholds()->maxPingMs(),
                'maxJitterMs' => $connection->thresholds()->maxJitterMs(),
                'maxPacketLossRatio' => $connection->thresholds()->maxPacketLossRatio(),
            ],
            'adaptivePolicy' => [
                'adaptiveIntervalSeconds' => $connection->adaptivePolicy()->adaptiveIntervalSeconds(),
                'recoveryHealthyCount' => $connection->adaptivePolicy()->recoveryHealthyCount(),
                'maxConsecutiveFailures' => $connection->adaptivePolicy()->maxConsecutiveFailures(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeRow(Probe $probe): array
    {
        return [
            'id' => $probe->id()->toString(),
            'name' => $probe->name(),
            'enabled' => $probe->isEnabled(),
        ];
    }
}
