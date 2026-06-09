<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\ProbeLiveness;
use App\Dashboard\Application\ReadModel\ProbeLivenessRepository;
use App\Dashboard\Application\ReadModel\RunStatusRepository;
use App\Shared\Domain\InvalidId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;

final class DashboardStatusApiAction extends AbstractController
{
    public function __construct(
        private readonly RunStatusRepository $runStatus,
        private readonly ProbeLivenessRepository $probeLiveness,
    ) {}

    #[Route("/dashboard/run-status", name: "dashboard_run_status", methods: ["GET"])]
    public function runStatus(Request $request): Response
    {
        try {
            $connectionId = new ConnectionId((string)$request->query->get("connectionId", ""));
        } catch (InvalidId) {
            return $this->badRequest("Missing or invalid connection id");
        }

        $status = $this->runStatus->forConnection($connectionId);

        return $this->noCacheJson([
            "state" => $status->state,
            "startedAtUnix" => $status->startedAtUnix,
        ]);
    }

    #[Route("/dashboard/probes-liveness", name: "dashboard_probes_liveness", methods: ["GET"])]
    public function probesLiveness(): Response
    {
        $probes = array_map(
            static fn(ProbeLiveness $probe): array => [
                "probeId" => $probe->probeId,
                "name" => $probe->name,
                "isOnline" => $probe->isOnline,
                "lastPollAtUnix" => $probe->lastPollAtUnix,
                "minutesSincePoll" => $probe->minutesSincePoll,
            ],
            $this->probeLiveness->all(),
        );

        return $this->noCacheJson(["probes" => $probes]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function noCacheJson(array $payload): JsonResponse
    {
        $response = new JsonResponse($payload, Response::HTTP_OK);
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(["error" => $message], Response::HTTP_BAD_REQUEST);
    }
}
