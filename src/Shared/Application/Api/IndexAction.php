<?php

declare(strict_types=1);

namespace App\Shared\Application\Api;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class IndexAction extends AbstractController
{
    #[Route("/v1", name: "status.get", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1",
        description: "Healthcheck endpoint that verifies if the application is running.",
        summary: "Check application status",
        tags: ["System"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application is running correctly",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "OK"),
                    ],
                    type: "object",
                ),
            ),
        ],
    )]
    public function status(): Response
    {
        return new JsonResponse([
            "status" => "OK",
        ]);
    }
}
