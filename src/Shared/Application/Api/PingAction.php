<?php

declare(strict_types=1);

namespace App\Shared\Application\Api;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PingAction extends AbstractController
{
    #[Route("/v1/ping", name: "ping.get", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/ping",
        description: "Liveness probe. Returns 200 immediately without checking any dependency.",
        summary: "Liveness check",
        tags: ["System"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application process is up",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "ok"),
                    ],
                    type: "object",
                ),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        return new JsonResponse([
            "status" => "ok",
        ]);
    }
}
