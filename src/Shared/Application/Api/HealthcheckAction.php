<?php

declare(strict_types=1);

namespace App\Shared\Application\Api;

use App\Shared\Application\Health\HealthCheckRunner;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthcheckAction extends AbstractController
{
    public function __construct(
        private readonly HealthCheckRunner $runner,
    ) {}

    #[Route('/v1/healthcheck', name: 'healthcheck.get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/healthcheck',
        description: 'Readiness probe. Runs the registered dependency health checks and returns 200 when all pass, 503 otherwise.',
        summary: 'Readiness check',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'All dependencies are healthy', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                    new OA\Property(property: 'checks', type: 'object', example: ['database' => ['status' => 'up']]),
                ],
                type: 'object',
            )),
            new OA\Response(
                response: 503,
                description: 'At least one dependency is unhealthy',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'unhealthy'),
                    new OA\Property(property: 'checks', type: 'object', example: ['database' => [
                        'status' => 'down',
                        'error' => 'connection refused',
                    ]]),
                ], type: 'object'),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        $report = $this->runner->run();

        return new JsonResponse(
            $report->toArray(),
            $report->isHealthy() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
