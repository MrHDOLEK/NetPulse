<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Connection\Domain\Enum\ConnectionColor;
use App\Dashboard\Application\ReadModel\ConnectionOverview;
use App\Dashboard\Application\ReadModel\ConnectionOverviewRepository;
use App\Dashboard\Application\ReadModel\ConnectionSeriesRepository;
use App\Dashboard\Application\ReadModel\Enum\SeriesMetric;
use App\Dashboard\Application\ReadModel\Enum\SeriesRange;
use App\Dashboard\Application\ReadModel\RecentHealthRepository;
use App\Dashboard\Application\Response\DashboardBootstrap;
use App\Scheduling\Domain\ValueObject\HealthHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardAction extends AbstractController
{
    public function __construct(
        private readonly ConnectionOverviewRepository $overview,
        private readonly ConnectionSeriesRepository $series,
        private readonly RecentHealthRepository $recentHealth,
    ) {}

    #[Route("/", name: "dashboard", methods: ["GET"])]
    public function __invoke(): Response
    {
        $range = SeriesRange::Week;
        $overview = $this->overview->overview($range);
        $default = $this->pickDefault($overview);

        $series = null;
        $recent = HealthHistory::empty();
        $bootstrap = null;

        if ($default !== null) {
            $series = $this->series->series($default->connectionId, $range, SeriesMetric::Speed);
            $recent = $this->recentHealth->recent($default->connectionId);
            $bootstrap = DashboardBootstrap::fromSpeedSeries($default->connectionId, $range, $series);
        }

        return $this->render("dashboard/index.html.twig", [
            "overview" => $overview,
            "range" => $range,
            "default" => $default,
            "series" => $series,
            "recent" => $recent,
            "bootstrap" => $bootstrap?->toArray(),
        ]);
    }

    /**
     * @param iterable<ConnectionOverview> $overview
     */
    private function pickDefault(iterable $overview): ?ConnectionOverview
    {
        $first = null;

        foreach ($overview as $item) {
            $first ??= $item;

            if ($item->color === ConnectionColor::Primary) {
                return $item;
            }
        }

        return $first;
    }
}
