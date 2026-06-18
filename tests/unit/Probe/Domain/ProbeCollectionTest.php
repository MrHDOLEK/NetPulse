<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Domain;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeCollection;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function count;

final class ProbeCollectionTest extends TestCase
{
    private const string PROBE_A = '550e8400-e29b-41d4-a716-446655440001';
    private const string PROBE_B = '550e8400-e29b-41d4-a716-446655440002';

    public function testOfAndFromListRoundTripTheProbes(): void
    {
        $a = $this->probe(self::PROBE_A, 'edge-a');
        $b = $this->probe(self::PROBE_B, 'edge-b');

        $collection = ProbeCollection::of($a, $b);

        $this->assertCount(2, $collection);
        $items = $collection->toArray();
        $this->assertSame($a, $items[0]);
        $this->assertSame($b, $items[1]);
        $this->assertSame(self::PROBE_A, $items[0]->id()->toString());
    }

    public function testFromListAcceptsAList(): void
    {
        $collection = ProbeCollection::fromList([
            $this->probe(self::PROBE_A, 'edge-a'),
            $this->probe(self::PROBE_B, 'edge-b'),
        ]);

        $names = [];

        foreach ($collection as $probe) {
            $names[] = $probe->name();
        }

        $this->assertSame(['edge-a', 'edge-b'], $names);
    }

    public function testEmptyCollectionIsIterableAndEmpty(): void
    {
        $collection = ProbeCollection::fromList([]);

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
        $this->assertSame(0, count($collection->toArray()));
    }

    private function probe(string $id, string $name): Probe
    {
        return new Probe(
            new ProbeId($id),
            $name,
            Labels::empty(),
            'hash',
            true,
            new DateTimeImmutable('2026-06-05T10:00:00+00:00'),
        );
    }
}
