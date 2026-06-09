<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension("symfonycasts_tailwind", [
        
        
        "binary_version" => "v3.4.17",
        "input_css" => [
            "%kernel.project_dir%/assets/styles/app.css",
        ],
    ]);
};
