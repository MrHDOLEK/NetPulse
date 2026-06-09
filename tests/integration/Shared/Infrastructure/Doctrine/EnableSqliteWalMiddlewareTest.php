<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EnableSqliteWalMiddlewareTest extends KernelTestCase
{
    public function testJournalModeIsWalOnConnect(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get("doctrine.dbal.default_connection");
        self::assertInstanceOf(Connection::class, $connection);

        $journalMode = $connection->executeQuery("PRAGMA journal_mode")->fetchOne();

        self::assertIsString($journalMode);
        self::assertSame("wal", strtolower($journalMode));
    }
}
