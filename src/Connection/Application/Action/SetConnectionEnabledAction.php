<?php

declare(strict_types=1);

namespace App\Connection\Application\Action;

use App\Connection\Application\Command\SetConnectionEnabled\SetConnectionEnabledCommand;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Infrastructure\Symfony\Request\SetConnectionEnabledRequest;
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

#[IsGranted('ROLE_ADMIN')]
final class SetConnectionEnabledAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'connection-enabled';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route('/settings/connections/{connectionId}/enabled', name: 'settings_connections_enabled', methods: ['POST'])]
    public function __invoke(string $connectionId, Request $request, SetConnectionEnabledRequest $payload): Response
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $id = new ConnectionId($connectionId);
            $probeId = new ProbeId($payload->probeId);
        } catch (InvalidId) {
            return new JsonResponse(['error' => 'Invalid connection or probe id'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new SetConnectionEnabledCommand($id, $probeId, $payload->enabled));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof NotFoundException) {
                return new JsonResponse(['error' => 'Connection not found'], Response::HTTP_NOT_FOUND);
            }

            throw $exception;
        }

        $response = new JsonResponse(['enabled' => $payload->enabled], Response::HTTP_OK);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }
}
