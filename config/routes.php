<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator
        ->import([
            'path' => '../src/Shared/Application/Api/',
            'namespace' => 'App\Shared\Application\Api',
        ], 'attribute')
        ->prefix('/api');

    $routingConfigurator
        ->import([
            'path' => '../src/Probe/Application/Api/',
            'namespace' => 'App\Probe\Application\Api',
        ], 'attribute')
        ->prefix('/api');

    $routingConfigurator->import([
        'path' => '../src/Probe/Application/Action/',
        'namespace' => 'App\Probe\Application\Action',
    ], 'attribute');

    $routingConfigurator->import([
        'path' => '../src/Connection/Application/Action/',
        'namespace' => 'App\Connection\Application\Action',
    ], 'attribute');

    $routingConfigurator
        ->import([
            'path' => '../src/Measurement/Application/Api/',
            'namespace' => 'App\Measurement\Application\Api',
        ], 'attribute')
        ->prefix('/api');

    $routingConfigurator->import([
        'path' => '../src/Measurement/Application/Action/',
        'namespace' => 'App\Measurement\Application\Action',
    ], 'attribute');

    $routingConfigurator
        ->import([
            'path' => '../src/Scheduling/Application/Api/',
            'namespace' => 'App\Scheduling\Application\Api',
        ], 'attribute')
        ->prefix('/api');

    $routingConfigurator->import([
        'path' => '../src/Metrics/Application/Action/',
        'namespace' => 'App\Metrics\Application\Action',
    ], 'attribute');

    $routingConfigurator->import([
        'path' => '../src/Auth/Application/Action/',
        'namespace' => 'App\Auth\Application\Action',
    ], 'attribute');

    $routingConfigurator->import([
        'path' => '../src/Dashboard/Application/Action/',
        'namespace' => 'App\Dashboard\Application\Action',
    ], 'attribute');

    $routingConfigurator->import([
        'path' => '../src/Settings/Application/Action/',
        'namespace' => 'App\Settings\Application\Action',
    ], 'attribute');
};
