<?php

declare(strict_types=1);

use App\Metrics\Application\RemoteWrite\PushMeasurementMessage;
use App\Notification\Application\Command\Notify\NotifyOnMeasurementCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'default_bus' => 'command.bus',
            'buses' => [
                'command.bus' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                    ],
                ],
                'event.bus' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => true,
                    ],
                ],
            ],
            'transports' => [
                'async' => [
                    'dsn' => '%env(MESSENGER_TRANSPORT_DSN)%',
                    'retry_strategy' => [
                        'max_retries' => 5,
                        'delay' => 2000,
                        'multiplier' => 2,
                        'max_delay' => 60000,
                    ],
                ],
            ],
            'routing' => [
                PushMeasurementMessage::class => 'async',
                NotifyOnMeasurementCommand::class => 'async',
            ],
        ],
    ]);
};
