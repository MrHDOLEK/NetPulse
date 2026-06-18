<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Doctrine\Type\EmailType;
use App\Auth\Infrastructure\Doctrine\Type\PasswordType;
use App\Auth\Infrastructure\Doctrine\Type\TotpSecretType;
use App\Auth\Infrastructure\Doctrine\Type\UserIdType;
use App\Auth\Infrastructure\Doctrine\Type\UserRoleCollectionType;
use App\Connection\Infrastructure\Doctrine\Type\AdaptivePolicyType;
use App\Connection\Infrastructure\Doctrine\Type\ConnectionIdType;
use App\Connection\Infrastructure\Doctrine\Type\ScheduleType;
use App\Connection\Infrastructure\Doctrine\Type\ServerPoolType;
use App\Connection\Infrastructure\Doctrine\Type\ThresholdsType;
use App\Measurement\Infrastructure\Doctrine\Type\MeasurementIdType;
use App\Probe\Infrastructure\Doctrine\Type\ProbeIdType;
use App\Settings\Infrastructure\Doctrine\Type\SettingKeyType;
use App\Shared\Infrastructure\Doctrine\Type\LabelsType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'dbal' => [
            'driver' => 'pdo_sqlite',
            'url' => '%env(resolve:DATABASE_URL)%',
            'charset' => 'utf8',
            'profiling_collect_backtrace' => '%kernel.debug%',
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
            ],
            'types' => [
                'user_id' => UserIdType::class,
                'user_role_collection' => UserRoleCollectionType::class,
                'email' => EmailType::class,
                'password' => PasswordType::class,
                'totp_secret' => TotpSecretType::class,
                'probe_id' => ProbeIdType::class,
                'connection_id' => ConnectionIdType::class,
                'measurement_id' => MeasurementIdType::class,
                'labels' => LabelsType::class,
                'server_pool' => ServerPoolType::class,
                'schedule' => ScheduleType::class,
                'thresholds' => ThresholdsType::class,
                'adaptive_policy' => AdaptivePolicyType::class,
                'setting_key' => SettingKeyType::class,
            ],
        ],
        'orm' => [
            'validate_xml_mapping' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'auto_mapping' => false,
            'mappings' => [
                'Auth' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Auth/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Auth\\Domain",
                    'is_bundle' => false,
                ],
                'Probe' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Probe/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Probe\\Domain",
                    'is_bundle' => false,
                ],
                'Connection' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Connection/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Connection\\Domain",
                    'is_bundle' => false,
                ],
                'Measurement' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Measurement/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Measurement\\Domain",
                    'is_bundle' => false,
                ],
                'Metrics' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Metrics/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Metrics\\Domain",
                    'is_bundle' => false,
                ],
                'Notification' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Notification/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Notification\\Domain",
                    'is_bundle' => false,
                ],
                'Scheduling' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Scheduling/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Scheduling\\Domain",
                    'is_bundle' => false,
                ],
                'Settings' => [
                    'type' => 'xml',
                    'dir' => '%kernel.project_dir%/src/Settings/Infrastructure/Doctrine/Mapping',
                    'prefix' => "App\\Settings\\Domain",
                    'is_bundle' => false,
                ],
            ],
        ],
    ]);

    if ($containerConfigurator->env() === 'prod') {
        $containerConfigurator->extension('doctrine', [
            'orm' => [
                'query_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.system_cache_pool',
                ],
                'result_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.result_cache_pool',
                ],
            ],
        ]);
        $containerConfigurator->extension('framework', [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                    'doctrine.system_cache_pool' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ]);
    }
};
