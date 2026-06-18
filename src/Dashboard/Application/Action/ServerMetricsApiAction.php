<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRepository;
use App\Dashboard\Application\Response\ServerMetricsResponse;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServerMetricsApiAction extends AbstractController
{
    public function __construct(
        private readonly ServerMetricsRepository $servers,
        private readonly ClockInterface $clock,
    ) {}

    #[Route('/dashboard/servers', name: 'dashboard_servers', methods: ['GET'])]
    public function servers(Request $request): Response
    {
        $window = HeatmapWindow::tryFrom($request->query->get('window', ''));

        if ($window === null) {
            return $this->badRequest('Unknown or missing window');
        }

        $rows = $this->servers->all($window);
        $nowUnix = $this->clock->now()->getTimestamp();

        return $this->noCacheJson(ServerMetricsResponse::from($rows, $window, $nowUnix)->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function noCacheJson(array $payload): JsonResponse
    {
        $response = new JsonResponse($payload, Response::HTTP_OK);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
