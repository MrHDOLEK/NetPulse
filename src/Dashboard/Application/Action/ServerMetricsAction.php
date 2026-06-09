<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Action;

use App\Dashboard\Application\ReadModel\Enum\HeatmapWindow;
use App\Dashboard\Application\ReadModel\ServerMetricsRepository;
use App\Dashboard\Application\Response\ServerMetricsResponse;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServerMetricsAction extends AbstractController
{
    public function __construct(
        private readonly ServerMetricsRepository $servers,
        private readonly ClockInterface $clock,
    ) {}

    #[Route("/servers", name: "servers", methods: ["GET"])]
    public function __invoke(): Response
    {
        $window = HeatmapWindow::Month;

        $rows = $this->servers->all($window);
        $nowUnix = $this->clock->now()->getTimestamp();

        $bootstrap = ServerMetricsResponse::from($rows, $window, $nowUnix)->toArray();

        return $this->render("servers/index.html.twig", [
            "window" => $window->value,
            "bootstrap" => $bootstrap,
        ]);
    }
}
