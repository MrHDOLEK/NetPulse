<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Dashboard\Application\ReadModel\ConnectionListRepository;
use App\Dashboard\Application\ReadModel\Enum\MeasurementSort;
use App\Dashboard\Application\ReadModel\MeasurementFilter;
use App\Dashboard\Application\ReadModel\MeasurementListRepository;
use App\Dashboard\Application\ReadModel\PendingRunsRepository;
use App\Dashboard\Application\ReadModel\ServerListRepository;
use App\Dashboard\Application\Response\MeasurementListResponse;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HistoryAction extends AbstractController
{
    private const int DEFAULT_LIMIT = 25;
    private const int DEFAULT_WINDOW_DAYS = 7;

    public function __construct(
        private readonly MeasurementListRepository $measurements,
        private readonly ConnectionListRepository $connections,
        private readonly ServerListRepository $servers,
        private readonly PendingRunsRepository $pendingRuns,
        private readonly ClockInterface $clock,
    ) {}

    #[Route("/history", name: "history", methods: ["GET"])]
    public function __invoke(): Response
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone("UTC"));

        $filter = MeasurementFilter::lastDays(
            self::DEFAULT_WINDOW_DAYS,
            $now,
            null,
            null,
            null,
            null,
            null,
        );

        $sort = MeasurementSort::CompletedAtDesc;
        $items = $this->measurements->list($filter, self::DEFAULT_LIMIT, 0, $sort);
        $total = $this->measurements->countMatching($filter);

        $bootstrapItems = MeasurementListResponse::from($items, $total, self::DEFAULT_LIMIT, 0)->toArray()["items"];

        $bootstrap = [
            "filters" => [
                "since" => $filter->since->format("Y-m-d"),
                "until" => $filter->until->format("Y-m-d"),
                "connection" => "",
                "server" => "",
                "status" => "",
                "healthy" => "",
                "scheduled" => "",
            ],
            "limit" => self::DEFAULT_LIMIT,
            "offset" => 0,
            "sort" => $sort->value,
            "total" => $total,
            "items" => $bootstrapItems,
        ];

        return $this->render("history/index.html.twig", [
            "connections" => $this->connections->all(),
            "servers" => $this->servers->all(),
            "items" => $items,
            "total" => $total,
            "pendingRuns" => $this->pendingRuns->pending(),
            "bootstrap" => $bootstrap,
        ]);
    }
}
