<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\Format\HeatmapScale;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use App\Dashboard\Application\ReadModel\HeatmapRepository;
use App\Dashboard\Application\Response\HeatmapResponse;
use App\Shared\Domain\InvalidId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HeatmapApiAction extends AbstractController
{
    public function __construct(
        private readonly HeatmapRepository $heatmap,
    ) {}

    #[Route('/dashboard/heatmap', name: 'dashboard_heatmap', methods: ['GET'])]
    public function heatmap(Request $request): Response
    {
        $metric = HeatmapMetric::tryFrom($request->query->get('metric', ''));
        $window = HeatmapWindow::tryFrom($request->query->get('window', ''));

        if ($metric === null) {
            return $this->badRequest('Unknown or missing metric');
        }

        if ($window === null) {
            return $this->badRequest('Unknown or missing window');
        }

        try {
            $connectionId = new ConnectionId($request->query->get('connection', ''));
        } catch (InvalidId) {
            return $this->badRequest('Missing or invalid connection id');
        }

        $query = new HeatmapQuery($metric, $window, $connectionId);
        $grid = $this->heatmap->grid($query);
        $scale = HeatmapScale::forGrid($grid, $metric);

        return $this->noCacheJson(HeatmapResponse::fromGrid($query, $grid, $scale)->toArray());
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
