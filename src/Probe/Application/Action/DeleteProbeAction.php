<?php

declare(strict_types=1);

namespace App\Probe\Application\Action;

use App\Probe\Application\Command\DeleteProbe\DeleteProbeCommand;
use App\Probe\Domain\Exception\ProbeHasConnections;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[IsGranted("ROLE_ADMIN")]
final class DeleteProbeAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = "probe-delete";

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route("/settings/probes/{probeId}", name: "settings_probes_delete", methods: ["DELETE"])]
    public function __invoke(Request $request, string $probeId): Response
    {
        $token = $request->headers->get("X-CSRF-Token");

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(["error" => "Invalid CSRF token"], Response::HTTP_FORBIDDEN);
        }

        try {
            $id = new ProbeId($probeId);
        } catch (InvalidId) {
            return new JsonResponse(["error" => "Invalid probe id"], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new DeleteProbeCommand($id));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof ProbeNotFound) {
                return new JsonResponse(["error" => "Probe not found"], Response::HTTP_NOT_FOUND);
            }

            if ($cause instanceof ProbeHasConnections) {
                return new JsonResponse(["error" => $cause->getMessage()], Response::HTTP_CONFLICT);
            }

            throw $exception;
        }

        return new Response("", Response::HTTP_NO_CONTENT);
    }
}
