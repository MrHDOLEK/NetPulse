<?php

declare(strict_types=1);

use App\Auth\Application\Oidc\OidcProvider;
use App\Auth\Domain\UserRepository;
use App\Auth\Infrastructure\Oidc\OidcConfig;
use App\Settings\Application\SettingsReader;
use App\Settings\Application\SettingsWriter;
use App\Settings\Infrastructure\AppSettings;
use App\Settings\Infrastructure\Oidc\OidcConfigFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\Tests\Integration\\', __DIR__ . "/../tests/integration/*");

    
    
    
    $services->alias("test." . UserRepository::class, UserRepository::class)
        ->public();

    
    
    $services->alias("test." . ClockInterface::class, ClockInterface::class)
        ->public();

    
    
    
    
    
    $services->set(OidcConfig::class)
        ->factory([service(OidcConfigFactory::class), "create"])
        ->public();
    $services->alias(OidcProvider::class, App\Auth\Infrastructure\Oidc\LeagueOidcProvider::class)
        ->public();

    
    
    $services->alias("test." . SettingsReader::class, SettingsReader::class)->public();
    $services->alias("test." . SettingsWriter::class, SettingsWriter::class)->public();
    $services->alias("test." . AppSettings::class, AppSettings::class)->public();

    
    
    $services->set(OidcConfigFactory::class)->public();
};
