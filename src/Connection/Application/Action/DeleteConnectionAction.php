<?php

declare(strict_types=1);

namespace App\Connection\Application\Action;

use App\Connection\Application\Command\DeleteConnection\DeleteConnectionCommand;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Infrastructure\Symfony\Request\ConnectionOwnerRequest;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use App\Shared\Domain\NotFoundException;
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
final class DeleteConnectionAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = "connection-delete";

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route("/settings/connections/{connectionId}", name: "settings_connections_delete", methods: ["DELETE"])]
    public function __invoke(string $connectionId, Request $request, ConnectionOwnerRequest $payload): Response
    {
        $token = $request->headers->get("X-CSRF-Token");

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(["error" => "Invalid CSRF token"], Response::HTTP_FORBIDDEN);
        }

        try {
            $id = new ConnectionId($connectionId);
            $probeId = new ProbeId($payload->probeId);
        } catch (InvalidId) {
            return new JsonResponse(["error" => "Invalid connection or probe id"], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new DeleteConnectionCommand($id, $probeId));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof NotFoundException) {
                return new JsonResponse(["error" => "Connection not found"], Response::HTTP_NOT_FOUND);
            }

            throw $exception;
        }

        return new Response("", Response::HTTP_NO_CONTENT);
    }
}
