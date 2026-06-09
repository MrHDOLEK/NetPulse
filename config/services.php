<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Doctrine\RegisterTotpSecretTypeMiddleware;
use App\Auth\Infrastructure\Oidc\OidcConfig;
use App\Connection\Infrastructure\Symfony\Request\ConnectionInputMapper;
use App\Measurement\Domain\Service\HealthEvaluator;
use App\Settings\Application\SettingsReader;
use App\Settings\Application\SettingsWriter;
use App\Settings\Infrastructure\AppSettings;
use App\Settings\Infrastructure\Oidc\OidcConfigFactory;
use App\Shared\Application\Health\HealthCheck;
use App\Shared\Application\Health\HealthCheckRunner;
use App\Shared\Infrastructure\Doctrine\EnableSqliteWalMiddleware;
use App\Shared\Infrastructure\Symfony\Listener\ExceptionListener;
use App\Shared\Infrastructure\Symfony\Request\Resolver\JsonBodyResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    
    
    
    $services->instanceof(HealthCheck::class)
        ->tag("app.health_check");

    
    
    $services->load("App\\", __DIR__ . "/../src/")
        ->exclude([
            __DIR__ . "/../src/Kernel.php",
            __DIR__ . "/../src/Probe/Domain/",
            __DIR__ . "/../src/Probe/Infrastructure/Symfony/Request/",
            __DIR__ . "/../src/Probe/Infrastructure/Doctrine/Mapping/",
            __DIR__ . "/../src/Connection/Domain/",
            __DIR__ . "/../src/Connection/Infrastructure/Symfony/Request/",
            __DIR__ . "/../src/Connection/Infrastructure/Doctrine/Mapping/",
            __DIR__ . "/../src/Measurement/Domain/",
            __DIR__ . "/../src/Measurement/Infrastructure/Symfony/Request/",
            __DIR__ . "/../src/Measurement/Infrastructure/Doctrine/Mapping/",
            __DIR__ . "/../src/Metrics/Domain/",
            __DIR__ . "/../src/Metrics/Application/RemoteWrite/PushMeasurementMessage.php",
            __DIR__ . "/../src/Dev/Domain/",
            __DIR__ . "/../src/Settings/Domain/",
            __DIR__ . "/../src/Settings/Infrastructure/Doctrine/Mapping/",
        ]);

    $services->set(ExceptionListener::class)
        ->arg('$environment', "%kernel.environment%")
        ->tag("kernel.event_listener", [
            "event" => "kernel.exception",
        ]);

    $services->set(JsonBodyResolver::class)
        ->tag("controller.argument_value_resolver", [
            "priority" => -50,
        ]);

    $services->set(EnableSqliteWalMiddleware::class)
        ->tag("doctrine.middleware");

    
    
    
    $services->set(RegisterTotpSecretTypeMiddleware::class)
        ->tag("doctrine.middleware");

    $services->set(HealthCheckRunner::class)
        ->arg('$checks', tagged_iterator("app.health_check"));

    
    
    $services->set(HealthEvaluator::class);

    
    
    
    $services->set(ConnectionInputMapper::class);

    
    
    
    $services->set(\Prometheus\CollectorRegistry::class)
        ->args([inline_service(\Prometheus\Storage\InMemory::class), false]);

    
    
    
    $services->alias(SettingsReader::class, AppSettings::class);
    $services->alias(SettingsWriter::class, AppSettings::class);

    
    
    
    $services->set(OidcConfig::class)
        ->factory([service(OidcConfigFactory::class), "create"]);
};
