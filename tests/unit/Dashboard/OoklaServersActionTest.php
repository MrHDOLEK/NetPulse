<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\Action\OoklaServersAction;
use App\Dashboard\Application\OoklaServer;
use App\Dashboard\Application\OoklaServerCatalog;
use PHPUnit\Framework\TestCase;

use function json_decode;

final class OoklaServersActionTest extends TestCase
{
    public function testMapsTheCatalogToJson(): void
    {
        $action = new OoklaServersAction($this->catalog(
            new OoklaServer(15317, 'T-Mobile Polska S.A.', 'Poznań', 'poz1.t-mobile.pl'),
            new OoklaServer(3599, 'KAMNET', 'Lubin', 'speedtest1.kamnet.pl'),
        ));

        $response = $action();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        self::assertSame(
            [
                'servers' => [
                    [
                        'id' => 15317,
                        'name' => 'T-Mobile Polska S.A.',
                        'location' => 'Poznań',
                        'host' => 'poz1.t-mobile.pl',
                    ],
                    ['id' => 3599, 'name' => 'KAMNET', 'location' => 'Lubin', 'host' => 'speedtest1.kamnet.pl'],
                ],
            ],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testEmptyCatalogReturnsEmptyList(): void
    {
        $response = (new OoklaServersAction($this->catalog()))();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['servers' => []], json_decode((string) $response->getContent(), true));
    }

    private function catalog(OoklaServer ...$servers): OoklaServerCatalog
    {
        return new class($servers) implements OoklaServerCatalog {
            /**
             * @param list<OoklaServer> $servers
             */
            public function __construct(
                private readonly array $servers,
            ) {}

            public function servers(): array
            {
                return $this->servers;
            }
        };
    }
}
