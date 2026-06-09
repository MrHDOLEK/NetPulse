<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Logging\LogfmtFormatter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;


return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    
    $services->set(LogfmtFormatter::class);

    $containerConfigurator->extension("monolog", [
        "channels" => ["deprecation"],
        "handlers" => [
            
            
            
            
            "stdout" => [
                "type" => "filter",
                "handler" => "stdout_stream",
                "min_level" => "debug",
                "max_level" => "info",
                "channels" => ["!event", "!doctrine"],
            ],
            "stdout_stream" => [
                "type" => "stream",
                "path" => "php://stdout",
                "level" => "debug",
                "formatter" => LogfmtFormatter::class,
            ],
            
            "stderr" => [
                "type" => "stream",
                "path" => "php://stderr",
                "level" => "warning",
                "formatter" => LogfmtFormatter::class,
                "channels" => ["!event"],
            ],
        ],
    ]);
};
