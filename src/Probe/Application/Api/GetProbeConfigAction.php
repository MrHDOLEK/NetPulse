<?php

declare(strict_types=1);

namespace App\Probe\Application\Api;

use App\Probe\Application\Config\ProbeConfigProvider;
use App\Probe\Domain\Entity\Probe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetProbeConfigAction extends AbstractController
{
    public function __construct(
        private readonly ProbeConfigProvider $configProvider,
    ) {}

    #[Route('/v1/probes/{probeId}/config', name: 'probe.config', methods: ['GET'])]
    public function __invoke(Probe $probe): Response
    {
        return new JsonResponse($this->configProvider->forProbe($probe)->toArray(), Response::HTTP_OK);
    }
}
