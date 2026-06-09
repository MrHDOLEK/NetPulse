<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Dashboard\Application\OoklaServer;
use App\Dashboard\Application\OoklaServerCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_map;

#[IsGranted("ROLE_ADMIN")]
final class OoklaServersAction extends AbstractController
{
    public function __construct(
        private readonly OoklaServerCatalog $catalog,
    ) {}

    #[Route("/dashboard/ookla-servers", name: "dashboard_ookla_servers", methods: ["GET"])]
    public function __invoke(): Response
    {
        $servers = array_map(
            static fn(OoklaServer $server): array => [
                "id" => $server->id,
                "name" => $server->name,
                "location" => $server->location,
                "host" => $server->host,
            ],
            $this->catalog->servers(),
        );

        $response = new JsonResponse(["servers" => $servers], Response::HTTP_OK);
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }
}
