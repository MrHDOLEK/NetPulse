<?php

declare(strict_types=1);

namespace App\Connection\Application\Action;

use App\Connection\Application\Command\CreateConnection\ConnectionCreated;
use App\Connection\Application\Command\CreateConnection\CreateConnectionCommand;
use App\Connection\Domain\Exception\InvalidAdaptivePolicy;
use App\Connection\Domain\Exception\InvalidExpectedSpeed;
use App\Connection\Domain\Exception\InvalidSchedule;
use App\Connection\Domain\Exception\InvalidThresholds;
use App\Connection\Infrastructure\Symfony\Request\ConnectionInputMapper;
use App\Connection\Infrastructure\Symfony\Request\ConnectionRequest;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\InvalidId;
use App\Shared\Domain\NotFoundException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ValueError;

use function is_string;

#[IsGranted('ROLE_ADMIN')]
final class CreateConnectionAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'connection-create';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ConnectionInputMapper $inputMapper,
    ) {}

    #[Route('/settings/connections', name: 'settings_connections_create', methods: ['POST'])]
    public function __invoke(Request $request, ConnectionRequest $payload): Response
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return $this->errorJson('Invalid CSRF token', Response::HTTP_FORBIDDEN);
        }

        try {
            $probeId = new ProbeId($payload->probeId);
        } catch (InvalidId) {
            return $this->errorJson('Invalid probe id', Response::HTTP_BAD_REQUEST);
        }

        try {
            $draft = $this->inputMapper->assemble($payload);
        } catch (
            ValueError|InvalidArgumentException|InvalidThresholds|InvalidAdaptivePolicy|InvalidSchedule|InvalidExpectedSpeed $exception
        ) {
            return $this->errorJson($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $command = new CreateConnectionCommand(
            $probeId,
            $draft->name,
            $draft->isp,
            $draft->expected,
            $draft->color,
            $draft->labels,
            $draft->serverPool,
            $draft->schedule,
            $draft->thresholds,
            $draft->adaptivePolicy,
        );

        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof NotFoundException) {
                return $this->errorJson('Probe not found', Response::HTTP_NOT_FOUND);
            }

            throw $exception;
        }

        $result = $envelope->last(HandledStamp::class)?->getResult();
        $id = $result instanceof ConnectionCreated ? $result->connectionId->toString() : null;

        $response = new JsonResponse(['id' => $id], Response::HTTP_CREATED);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    private function errorJson(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
