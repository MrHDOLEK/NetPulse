<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;


return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension("framework", [
        "notifier" => [
            "chatter_transports" => [
                
                
                "chat" => "%env(default:notify_chat_dsn:NOTIFY_CHAT_DSN)%",
            ],
        ],
    ]);

    $containerConfigurator->parameters()
        ->set("notify_chat_dsn", "null://null");
};
