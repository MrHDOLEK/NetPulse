<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\ReadModel\ConnectionOverviewRepository;
use App\Dashboard\Application\ReadModel\ConnectionSeriesRepository;
use App\Dashboard\Application\ReadModel\DashboardCursorRepository;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\Response\SeriesResponse;
use App\Dashboard\Application\Response\SnapshotResponse;
use App\Scheduling\Application\Command\RequestImmediateTest\RequestImmediateTestCommand;
use App\Shared\Domain\InvalidId;
use App\Shared\Domain\NotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function is_string;
use function trim;

final class DashboardApiAction extends AbstractController
{
    private const string RUN_TEST_TOKEN_ID = 'run-test';
    private const string SCOPE_CONNECTION = 'connection';
    private const string SCOPE_ALL = 'all';

    public function __construct(
        private readonly ConnectionSeriesRepository $series,
        private readonly ConnectionOverviewRepository $overview,
        private readonly DashboardCursorRepository $cursor,
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route('/dashboard/series', name: 'dashboard_series', methods: ['GET'])]
    public function series(Request $request): Response
    {
        $range = SeriesRange::tryFrom($request->query->get('range', ''));
        $metric = SeriesMetric::tryFrom($request->query->get('metric', ''));

        if ($range === null) {
            return $this->badRequest('Unknown or missing range');
        }

        if ($metric === null) {
            return $this->badRequest('Unknown or missing metric');
        }

        try {
            $connectionId = new ConnectionId($request->query->get('connection', ''));
        } catch (InvalidId) {
            return $this->badRequest('Missing or invalid connection id');
        }

        $series = $this->series->series($connectionId, $range, $metric);

        return $this->noCacheJson(SeriesResponse::fromCollection($connectionId, $range, $metric, $series)->toArray());
    }

    #[Route('/dashboard/snapshot', name: 'dashboard_snapshot', methods: ['GET'])]
    public function snapshot(): Response
    {
        $overview = $this->overview->overview(SeriesRange::Week);

        return $this->noCacheJson(SnapshotResponse::fromOverview($overview)->toArray());
    }

    #[Route('/dashboard/cursor', name: 'dashboard_cursor', methods: ['GET'])]
    public function cursor(): Response
    {
        $cursor = $this->cursor->current();

        return $this->noCacheJson([
            'latestCompletedAtUnix' => $cursor->latestCompletedAtUnix,
            'totalMeasurementCount' => $cursor->totalCount,
        ]);
    }

    #[Route('/dashboard/run', name: 'dashboard_run', methods: ['POST'])]
    public function run(Request $request): Response
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (!is_string($token) || !$this->isCsrfTokenValid(self::RUN_TEST_TOKEN_ID, $token)) {
            return $this->errorJson('Invalid CSRF token', Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $scope = is_string($body['scope'] ?? null) ? $body['scope'] : '';

        if ($scope !== self::SCOPE_CONNECTION && $scope !== self::SCOPE_ALL) {
            return $this->badRequest('Unknown or missing scope');
        }

        $rawServerId = is_string($body['serverId'] ?? null) ? trim($body['serverId']) : '';
        $serverId = $rawServerId === '' ? null : $rawServerId;

        if ($scope === self::SCOPE_ALL && $serverId !== null) {
            return $this->badRequest("A specific server cannot be pinned for the 'all' scope");
        }

        $connectionId = null;

        if ($scope === self::SCOPE_CONNECTION) {
            try {
                $rawConnectionId = is_string($body['connectionId'] ?? null) ? $body['connectionId'] : '';
                $connectionId = new ConnectionId($rawConnectionId)->toString();
            } catch (InvalidId) {
                return $this->badRequest('Invalid connection id');
            }
        }

        try {
            $this->commandBus->dispatch(new RequestImmediateTestCommand($scope, $connectionId, $serverId));
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof NotFoundException) {
                return $this->errorJson('Connection not found', Response::HTTP_NOT_FOUND);
            }

            throw $exception;
        }

        $response = new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function noCacheJson(array $payload): JsonResponse
    {
        $response = new JsonResponse($payload, Response::HTTP_OK);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
    }

    private function errorJson(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
