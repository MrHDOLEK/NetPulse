<?php

declare(strict_types=1);

namespace App\Probe\Application\Api;

use App\Probe\Domain\Entity\Probe;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function max;

final class ProbeHealthAction extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire('%env(int:AGENT_POLL_INTERVAL)%')]
        private readonly int $pollInterval,
    ) {}

    #[Route('/v1/probes/{probeId}/health', name: 'probe.health', methods: ['GET'])]
    public function __invoke(Probe $probe): Response
    {
        $lastPoll = $probe->lastPollAt();
        $secondsSince = $lastPoll === null ? null : $this->clock->now()->getTimestamp() - $lastPoll->getTimestamp();

        $staleAfter = max(180, $this->pollInterval * 3);
        $healthy = $secondsSince !== null && $secondsSince <= $staleAfter;

        return new JsonResponse(
            [
                'healthy' => $healthy,
                'lastPollAtUnix' => $lastPoll?->getTimestamp(),
                'secondsSincePoll' => $secondsSince,
                'staleAfterSeconds' => $staleAfter,
            ],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
