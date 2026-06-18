<?php

declare(strict_types=1);

namespace App\Metrics\Application\Action;

use App\Metrics\Application\MetricsRepository;
use App\Metrics\Infrastructure\Config\PrometheusConfig;
use App\Metrics\Infrastructure\Prometheus\TextFormatRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsAction
{
    private const string CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    public function __construct(
        private readonly MetricsRepository $readModel,
        private readonly TextFormatRenderer $renderer,
        private readonly PrometheusConfig $config,
    ) {}

    #[Route('/metrics', name: 'metrics.scrape', methods: ['GET'])]
    public function scrape(): Response
    {
        $body = $this->renderer->render(
            $this->readModel->latestPerConnection(),
            $this->readModel->runCounts(),
            $this->readModel->connectionsExpected(),
            $this->readModel->unhealthyCounts(),
            $this->readModel->connectionDegraded(),
            $this->readModel->remoteWriteFailures(),
            $this->readModel->notificationSends(),
            $this->config->freshnessWindowSeconds(),
        );

        return new Response($body, Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE]);
    }
}
