<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Dashboard\Application\Format\HeatmapScale;
use App\Dashboard\Application\ReadModel\ConnectionListRepository;
use App\Dashboard\Application\ReadModel\Enum\HeatmapMetric;
use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\Heatmap\HeatmapQuery;
use App\Dashboard\Application\ReadModel\HeatmapRepository;
use App\Dashboard\Application\Response\HeatmapResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HeatmapAction extends AbstractController
{
    public function __construct(
        private readonly HeatmapRepository $heatmap,
        private readonly ConnectionListRepository $connections,
    ) {}

    #[Route("/heatmap", name: "heatmap", methods: ["GET"])]
    public function __invoke(): Response
    {
        $metric = HeatmapMetric::Download;
        $window = HeatmapWindow::Month;

        $connections = $this->connections->all()->toArray();

        $connectionOptions = [];

        foreach ($connections as $connection) {
            $connectionOptions[] = [
                "id" => $connection->connectionId->toString(),
                "name" => $connection->name,
            ];
        }

        if ($connectionOptions === []) {
            return $this->render("heatmap/index.html.twig", [
                "metric" => $metric->value,
                "window" => $window->value,
                "connections" => [],
                "bootstrap" => null,
            ]);
        }

        $firstConnectionId = $connections[0]->connectionId;

        $query = new HeatmapQuery($metric, $window, $firstConnectionId);
        $grid = $this->heatmap->grid($query);
        $scale = HeatmapScale::forGrid($grid, $metric);

        $bootstrap = HeatmapResponse::fromGrid($query, $grid, $scale)->toArray();
        $bootstrap["connections"] = $connectionOptions;

        return $this->render("heatmap/index.html.twig", [
            "metric" => $metric->value,
            "window" => $window->value,
            "connections" => $connectionOptions,
            "bootstrap" => $bootstrap,
        ]);
    }
}
