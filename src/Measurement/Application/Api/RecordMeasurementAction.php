<?php

declare(strict_types=1);

namespace App\Measurement\Application\Api;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Application\Command\RecordMeasurement\RecordMeasurementCommand;
use App\Measurement\Infrastructure\Symfony\Request\RecordMeasurementRequest;
use App\Probe\Domain\Entity\Probe;
use App\Shared\Infrastructure\Symfony\Request\Validator\RequestValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

final class RecordMeasurementAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly RequestValidator $requestValidator,
    ) {}

    #[Route("/v1/probes/{probeId}/results", name: "measurement.record", methods: ["POST"])]
    public function __invoke(Probe $probe, RecordMeasurementRequest $request): Response
    {
        $this->requestValidator->validate($request);

        $this->commandBus->dispatch(new RecordMeasurementCommand(
            $probe->id(),
            new ConnectionId($request->connectionId),
            $request->ookla,
            $request->scheduled,
            $request->raw,
        ));

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
