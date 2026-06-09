<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Health;

use App\Shared\Infrastructure\Health\DatabaseHealthCheck;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseHealthCheckTest extends KernelTestCase
{
    public function testReportsUpAgainstWorkingConnection(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get("doctrine.dbal.default_connection");
        self::assertInstanceOf(Connection::class, $connection);

        $check = new DatabaseHealthCheck($connection);

        self::assertSame("database", $check->name());

        $result = $check->check();

        self::assertTrue($result->isHealthy());
        self::assertNull($result->error());
    }
}
