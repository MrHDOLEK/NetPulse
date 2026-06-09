<?php

declare(strict_types=1);

namespace App\Connection\Application\Action;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Enum\ScheduleMode;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\ProbeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_map;

#[IsGranted("ROLE_ADMIN")]
final class MetaConnectionsAction extends AbstractController
{
    public function __construct(
        private readonly ProbeRepository $probes,
    ) {}

    #[Route("/settings/connections/meta", name: "settings_connections_meta", methods: ["GET"])]
    public function __invoke(): Response
    {
        $probes = [];

        foreach ($this->probes->all() as $probe) {
            $probes[] = [
                "id" => $probe->id()->toString(),
                "name" => $probe->name(),
                "enabled" => $probe->isEnabled(),
            ];
        }

        $thresholds = Thresholds::default();
        $adaptive = AdaptivePolicy::default();

        $response = new JsonResponse([
            "probes" => $probes,
            "colors" => array_map(static fn(ConnectionColor $c): string => $c->value, ConnectionColor::cases()),
            "scheduleModes" => array_map(static fn(ScheduleMode $m): string => $m->value, ScheduleMode::cases()),
            "defaults" => [
                "thresholds" => [
                    "minDownloadRatio" => $thresholds->minDownloadRatio(),
                    "minUploadRatio" => $thresholds->minUploadRatio(),
                    "maxPingMs" => $thresholds->maxPingMs(),
                    "maxJitterMs" => $thresholds->maxJitterMs(),
                    "maxPacketLossRatio" => $thresholds->maxPacketLossRatio(),
                ],
                "adaptivePolicy" => [
                    "adaptiveIntervalSeconds" => $adaptive->adaptiveIntervalSeconds(),
                    "recoveryHealthyCount" => $adaptive->recoveryHealthyCount(),
                    "maxConsecutiveFailures" => $adaptive->maxConsecutiveFailures(),
                ],
                "scheduleMode" => ScheduleMode::Even->value,
                "testsPerDay" => 24,
                "jitterSeconds" => 120,
                "color" => ConnectionColor::default()->value,
            ],
        ], Response::HTTP_OK);
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }
}
