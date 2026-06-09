<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\Symfony\Listener;

use App\Metrics\Infrastructure\Config\PrometheusConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class PrometheusAllowedIpsListener
{
    private const string METRICS_PATH = "/metrics";

    public function __construct(
        private PrometheusConfig $config,
    ) {}

    #[AsEventListener(event: "kernel.request", priority: 100)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== self::METRICS_PATH) {
            return;
        }

        if (!$this->config->metricsEnabled()) {
            $event->setResponse(new Response("", Response::HTTP_NOT_FOUND));

            return;
        }

        $allowedCidrs = $this->config->allowedCidrs();

        if ($allowedCidrs === []) {
            return;
        }

        $clientIp = $request->getClientIp();

        if ($clientIp === null || !IpUtils::checkIp($clientIp, $allowedCidrs)) {
            $event->setResponse(new Response("", Response::HTTP_FORBIDDEN));
        }
    }
}
