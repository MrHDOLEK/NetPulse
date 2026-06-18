<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Scheduling\Domain\MarkedConnectionCollection;
use App\Scheduling\Domain\ValueObject\MarkedConnection;
use PHPUnit\Framework\TestCase;

use function count;

final class MarkedConnectionCollectionTest extends TestCase
{
    private const string CONN_A = '10000000-0000-7000-8000-0000000000a1';
    private const string CONN_B = '10000000-0000-7000-8000-0000000000a2';

    public function testFromListRoundTripsMarkedConnectionsWithTheirPins(): void
    {
        $pinned = new MarkedConnection(new ConnectionId(self::CONN_A), '12345');
        $unpinned = new MarkedConnection(new ConnectionId(self::CONN_B), null);

        $collection = MarkedConnectionCollection::fromList([$pinned, $unpinned]);

        $items = $collection->toArray();

        self::assertCount(2, $collection);
        self::assertSame($pinned, $items[0]);
        self::assertSame($unpinned, $items[1]);
        self::assertSame('12345', $items[0]->forcedServerId);
        self::assertNull($items[1]->forcedServerId);
        self::assertSame(self::CONN_A, $items[0]->connectionId->toString());
    }

    public function testEmptyCollectionIsIterableAndEmpty(): void
    {
        $collection = MarkedConnectionCollection::fromList([]);

        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
        self::assertSame(0, count($collection->toArray()));
    }

    public function testCollectionIsIterable(): void
    {
        $marked = new MarkedConnection(new ConnectionId(self::CONN_A), null);

        $seen = [];

        foreach (MarkedConnectionCollection::fromList([$marked]) as $item) {
            $seen[] = $item->connectionId->toString();
        }

        self::assertSame([self::CONN_A], $seen);
    }
}
