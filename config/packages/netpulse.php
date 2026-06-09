<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set("netpulse.prometheus.metrics_enabled", "%env(bool:PROMETHEUS_METRICS_ENABLED)%")
        ->set("netpulse.prometheus.allowed_ips", "%env(string:PROMETHEUS_ALLOWED_IPS)%")
        ->set("netpulse.prometheus.freshness_window", "%env(int:MEASUREMENT_FRESHNESS_WINDOW)%")
        ->set("netpulse.build.version", "%env(string:NETPULSE_VERSION)%")
        ->set("netpulse.remote_write.enabled", "%env(bool:REMOTE_WRITE_ENABLED)%")
        ->set("netpulse.remote_write.url", "%env(string:REMOTE_WRITE_URL)%")
        ->set("netpulse.remote_write.auth", "%env(string:REMOTE_WRITE_AUTH)%")
        ->set("netpulse.remote_write.extra_labels", "%env(string:REMOTE_WRITE_EXTRA_LABELS)%");
};
