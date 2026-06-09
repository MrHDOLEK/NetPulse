<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Dashboard\Application\Export\MeasurementCsvExporter;
use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use App\Dashboard\Application\ReadModel\MeasurementDetailRepository;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use App\Dashboard\Application\ReadModel\MeasurementNotFound;
use App\Dashboard\Application\Response\MeasurementDetailResponse;
use App\Dashboard\Application\Response\MeasurementListResponse;
use App\Measurement\Application\Share\ShareMeasurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use App\Measurement\Domain\Exception\MeasurementNotFound as MeasurementDomainNotFound;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Shared\Domain\InvalidId;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

use function fclose;
use function fopen;
use function fputcsv;
use function in_array;
use function sprintf;

final class HistoryApiAction extends AbstractController
{
    private const int DEFAULT_LIMIT = 25;
    private const int DEFAULT_WINDOW_DAYS = 7;
    private const array ALLOWED_LIMITS = [10, 25, 50];        
    private const string SHARE_TOKEN_ID = "run-test";

    public function __construct(
        private readonly MeasurementListRepository $measurements,
        private readonly MeasurementDetailRepository $details,
        private readonly MeasurementCsvExporter $csvExporter,
        private readonly ClockInterface $clock,
        private readonly ShareMeasurement $shareMeasurement,
    ) {}

    #[Route("/dashboard/history", name: "dashboard_history", methods: ["GET"])]
    public function history(Request $request): Response
    {
        $limit = (int)$request->query->get("limit", (string)self::DEFAULT_LIMIT);

        if (!in_array($limit, self::ALLOWED_LIMITS, true)) {
            return $this->badRequest("Unsupported limit (allowed: 10, 25, 50)");
        }

        $offset = (int)$request->query->get("offset", "0");

        if ($offset < 0) {
            return $this->badRequest("Offset must be zero or positive");
        }

        $sort = MeasurementSort::tryFrom((string)$request->query->get("sort", MeasurementSort::default()->value));

        if ($sort === null) {
            return $this->badRequest("Unknown sort");
        }

        try {
            $filter = $this->buildFilter($request);
        } catch (BadFilterRequest $exception) {
            return $this->badRequest($exception->getMessage());
        }

        $items = $this->measurements->list($filter, $limit, $offset, $sort);
        $total = $this->measurements->countMatching($filter);

        return $this->noCacheJson(
            MeasurementListResponse::from($items, $total, $limit, $offset)->toArray(),
        );
    }

    #[Route("/dashboard/history/{id}", name: "dashboard_history_detail", methods: ["GET"], requirements: ["id" => "[0-9a-fA-F-]{36}"])]
    public function detail(string $id): Response
    {
        try {
            $measurementId = new MeasurementId($id);
        } catch (InvalidId) {
            return $this->badRequest("Invalid measurement id");
        }

        try {
            $detail = $this->details->get($measurementId);
        } catch (MeasurementNotFound) {
            return new JsonResponse(["error" => "Measurement not found"], Response::HTTP_NOT_FOUND);
        }

        return $this->noCacheJson(MeasurementDetailResponse::from($detail)->toArray());
    }

    #[Route("/dashboard/history/{id}/share", name: "dashboard_history_share", methods: ["POST"], requirements: ["id" => "[0-9a-fA-F-]{36}"])]
    public function share(string $id, Request $request): Response
    {
        $token = $request->headers->get("X-CSRF-Token");

        if (!is_string($token) || !$this->isCsrfTokenValid(self::SHARE_TOKEN_ID, $token)) {
            return new JsonResponse(["error" => "Invalid CSRF token"], Response::HTTP_FORBIDDEN);
        }

        try {
            $measurementId = new MeasurementId($id);
        } catch (InvalidId) {
            return $this->badRequest("Invalid measurement id");
        }

        try {
            $shareToken = ($this->shareMeasurement)($measurementId);
        } catch (MeasurementDomainNotFound) {
            return new JsonResponse(["error" => "Measurement not found"], Response::HTTP_NOT_FOUND);
        }

        return $this->noCacheJson(["shareUrl" => "/r/" . $shareToken]);
    }

    #[Route("/dashboard/history/export.csv", name: "dashboard_history_csv", methods: ["GET"])]
    public function exportCsv(Request $request): Response
    {
        try {
            $filter = $this->buildFilter($request);
        } catch (BadFilterRequest $exception) {
            return $this->badRequest($exception->getMessage());
        }

        $exporter = $this->csvExporter;

        $response = new StreamedResponse(static function () use ($exporter, $filter): void {
            $out = fopen("php://output", "w");

            if ($out === false) {
                return;
            }

            fputcsv($out, $exporter->header(), escape: "");

            foreach ($exporter->rows($filter) as $row) {
                fputcsv($out, $row, escape: "");
            }

            fclose($out);
        });

        $filename = sprintf("netpulse-history-%d.csv", $this->clock->now()->getTimestamp());

        $response->headers->set("Content-Type", "text/csv; charset=utf-8");
        $response->headers->set("Content-Disposition", sprintf('attachment; filename="%s"', $filename));
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }

    /**
     * @throws BadFilterRequest on an invalid connection id, unknown status or an unparseable/backwards window
     */
    private function buildFilter(Request $request): MeasurementFilter
    {
        $statusParam = $request->query->get("status");
        $status = null;

        if ($statusParam !== null && $statusParam !== "") {
            $status = MeasurementStatus::tryFrom((string)$statusParam);

            if ($status === null) {
                throw new BadFilterRequest("Unknown status");
            }
        }

        $healthy = $this->parseBool($request->query->get("healthy"));
        $scheduled = $this->parseBool($request->query->get("scheduled"));

        $connectionParam = $request->query->get("connection");
        $connection = null;

        if ($connectionParam !== null && $connectionParam !== "") {
            try {
                $connection = new ConnectionId((string)$connectionParam);
            } catch (InvalidId) {
                throw new BadFilterRequest("Invalid connection id");
            }
        }

        $serverParam = $request->query->get("server");
        $server = ($serverParam !== null && $serverParam !== "") ? (string)$serverParam : null;

        return $this->buildWindow($request, $connection, $server, $status, $healthy, $scheduled);
    }

    /**
     * @throws BadFilterRequest on an unparseable date or a backwards window
     */
    private function buildWindow(
        Request $request,
        ?ConnectionId $connection,
        ?string $server,
        ?MeasurementStatus $status,
        ?bool $healthy,
        ?bool $scheduled,
    ): MeasurementFilter {
        $sinceParam = $request->query->get("since");
        $untilParam = $request->query->get("until");

        $utc = new DateTimeZone("UTC");
        $now = $this->clock->now()->setTimezone($utc);

        if (($sinceParam === null || $sinceParam === "") && ($untilParam === null || $untilParam === "")) {
            return MeasurementFilter::lastDays(
                self::DEFAULT_WINDOW_DAYS,
                $now,
                $connection,
                $server,
                $status,
                $healthy,
                $scheduled,
            );
        }

        try {
            $since = ($sinceParam !== null && $sinceParam !== "")
                ? new DateTimeImmutable((string)$sinceParam, $utc)
                : $now->modify("-" . self::DEFAULT_WINDOW_DAYS . " days");

            $until = ($untilParam !== null && $untilParam !== "")
                ? $this->parseUntil((string)$untilParam, $utc)
                : $now;

            return new MeasurementFilter($connection, $since, $until, $server, $status, $healthy, $scheduled);
        } catch (InvalidArgumentException | Exception) {
            throw new BadFilterRequest("Invalid time window");
        }
    }

    private function parseUntil(string $value, DateTimeZone $utc): DateTimeImmutable
    {
        $until = new DateTimeImmutable($value, $utc);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $until->modify("+1 day");
        }

        return $until;
    }

    private function parseBool(mixed $value): ?bool
    {
        if (!is_string($value) || $value === "") {
            return null;
        }

        return match ($value) {
            "1", "true" => true,
            "0", "false" => false,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function noCacheJson(array $payload): JsonResponse
    {
        $response = new JsonResponse($payload, Response::HTTP_OK);
        $response->headers->set("Cache-Control", "no-cache");

        return $response;
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(["error" => $message], Response::HTTP_BAD_REQUEST);
    }
}
