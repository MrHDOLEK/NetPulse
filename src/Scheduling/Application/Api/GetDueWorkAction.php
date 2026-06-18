<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Api;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbePollRecorder;
use App\Scheduling\Application\GetDueWork\GetDueWorkHandler;
use App\Scheduling\Application\GetDueWork\GetDueWorkQuery;
use App\Scheduling\Application\Response\DueWorkResponse;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetDueWorkAction extends AbstractController
{
    public function __construct(
        private readonly GetDueWorkHandler $getDueWork,
        private readonly ProbePollRecorder $pollRecorder,
        private readonly ClockInterface $clock,
    ) {}

    #[Route('/v1/probes/{probeId}/due', name: 'scheduling.due', methods: ['GET'])]
    public function __invoke(Probe $probe): Response
    {
        $this->pollRecorder->recordPoll($probe->id(), $this->clock->now());

        $dueWork = ($this->getDueWork)(new GetDueWorkQuery($probe->id()));

        return new JsonResponse(DueWorkResponse::fromDueWork($dueWork)->toArray(), Response::HTTP_OK);
    }
}
