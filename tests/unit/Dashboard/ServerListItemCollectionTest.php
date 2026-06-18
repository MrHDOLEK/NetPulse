<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\Application\ReadModel\ServerListItem;
use App\Dashboard\Application\ReadModel\ServerListItemCollection;
use PHPUnit\Framework\TestCase;

final class ServerListItemCollectionTest extends TestCase
{
    public function testFromListRoundTrips(): void
    {
        $a = new ServerListItem('12345', 'Acme Speedtest', 'Warsaw');
        $b = new ServerListItem('67890', 'Globe CDN', 'Berlin');
        $collection = ServerListItemCollection::fromList([$a, $b]);

        self::assertCount(2, $collection);
        self::assertSame([$a, $b], $collection->toArray());
        self::assertSame('12345', $collection->toArray()[0]->serverId);
        self::assertSame('Warsaw', $collection->toArray()[0]->location);
    }
}
