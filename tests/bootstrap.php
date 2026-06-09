<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . "/vendor/autoload.php";

if (method_exists(Dotenv::class, "bootEnv")) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . "/.env");
}

ensureTestSchema();

function ensureTestSchema(): void
{
    $kernel = new Kernel((string)($_SERVER["APP_ENV"] ?? "test"), (bool)($_SERVER["APP_DEBUG"] ?? false));
    $kernel->boot();

    /** @var Connection $connection */
    $connection = $kernel->getContainer()->get("doctrine")->getConnection();

    $schemaIsBuilt = $connection->createSchemaManager()->tablesExist(["probes"]);

    if (!$schemaIsBuilt) {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput([
                "command" => "doctrine:migrations:migrate",
                "--no-interaction" => true,
            ]),
            new NullOutput(),
        );
    }

    $kernel->shutdown();
}
