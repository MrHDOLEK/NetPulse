<?php

declare(strict_types=1);

namespace App\Probe\Application\Action;

use App\Probe\Application\Command\SetProbeEnabled\SetProbeEnabledCommand;
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

use function is_array;
use function is_bool;
use function is_string;

#[IsGranted('ROLE_ADMIN')]
final class SetProbeEnabledAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'probe-enabled';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route('/settings/probes/{probeId}/enabled', name: 'settings_probes_enabled', methods: ['POST'])]
    public function __invoke(Request $request, string $probeId): Response
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $id = new ProbeId($probeId);
        } catch (InvalidId) {
            return new JsonResponse(['error' => 'Invalid probe id'], Response::HTTP_BAD_REQUEST);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];
        $enabled = is_bool($body['enabled'] ?? null) ? $body['enabled'] : false;

        try {
            $this->commandBus->dispatch(new SetProbeEnabledCommand($id, $enabled));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof ProbeNotFound) {
                return new JsonResponse(['error' => 'Probe not found'], Response::HTTP_NOT_FOUND);
            }

            throw $exception;
        }

        $response = new JsonResponse(['enabled' => $enabled], Response::HTTP_OK);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }
}
