<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use SensitiveParameter;

final class EnableSqliteWalMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new EnableSqliteWalDriver($driver);
    }
}

/**
 * @phpstan-import-type Params from DriverManager
 */
final class EnableSqliteWalDriver extends AbstractDriverMiddleware
{
    /**
     * @phpstan-param Params $params
     */
    public function connect(#[SensitiveParameter] array $params): DriverConnection
    {
        $connection = parent::connect($params);

        if (!$this->getDatabasePlatform($connection) instanceof SQLitePlatform) {
            return $connection;
        }

        return new EnableSqliteWalConnection($connection);
    }
}

final class EnableSqliteWalConnection extends AbstractConnectionMiddleware
{
    public function __construct(DriverConnection $connection)
    {
        parent::__construct($connection);

        $connection->exec("PRAGMA journal_mode=WAL");
        $connection->exec("PRAGMA busy_timeout=5000");
    }
}
