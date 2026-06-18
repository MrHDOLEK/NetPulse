<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'mailer' => [
            'dsn' => '%env(default:mailer_dsn:MAILER_DSN)%',
        ],
    ]);

    $containerConfigurator->parameters()->set('mailer_dsn', 'null://null');
};
