<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Collection;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function iterator_to_array;

/**
 * @extends Collection<string>
 */
final class StringCollectionFixture extends Collection
{
    public static function of(string ...$values): self
    {
        return new self(array_values($values));
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}

/**
 * @extends Collection<int>
 */
final class IntCollectionFixture extends Collection
{
    public static function of(int ...$values): self
    {
        return new self(array_values($values));
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}

final class CollectionTest extends TestCase
{
    /**
     * @return iterable<string, array{StringCollectionFixture|IntCollectionFixture, list<int|string>}>
     */
    public static function provideCollections(): iterable
    {
        yield 'strings' => [StringCollectionFixture::of('a', 'b', 'c'), ['a', 'b', 'c']];
        yield 'ints' => [IntCollectionFixture::of(1, 2, 3, 4), [1, 2, 3, 4]];
        yield 'single' => [StringCollectionFixture::of('only'), ['only']];
    }

    /**
     * @param Collection<int|string> $collection
     * @param list<int|string> $expected
     */
    #[DataProvider('provideCollections')]
    public function testIteratesInInsertionOrder(Collection $collection, array $expected): void
    {
        self::assertSame($expected, iterator_to_array($collection));
        self::assertSame($expected, $collection->toArray());
    }

    /**
     * @param Collection<int|string> $collection
     * @param list<int|string> $expected
     */
    #[DataProvider('provideCollections')]
    public function testCountsElements(Collection $collection, array $expected): void
    {
        self::assertCount(count($expected), $collection);
        self::assertSame(count($expected), $collection->count());
    }

    public function testIsEmptyReflectsContents(): void
    {
        self::assertTrue(StringCollectionFixture::of()->isEmpty());
        self::assertFalse(StringCollectionFixture::of('x')->isEmpty());
    }

    public function testExposesArrayIteratorAndImplementsSplContracts(): void
    {
        $collection = IntCollectionFixture::of(7, 8);

        self::assertInstanceOf(IteratorAggregate::class, $collection);
        self::assertInstanceOf(Countable::class, $collection);
        self::assertInstanceOf(ArrayIterator::class, $collection->getIterator());
    }

    public function testIsImmutableWithNoMutatorsAndDefensiveCopy(): void
    {
        $reflection = new ReflectionClass(Collection::class);

        foreach (['add', 'remove', 'set', 'push', 'append', 'clear'] as $mutator) {
            self::assertFalse($reflection->hasMethod($mutator), "Collection must not expose mutator {$mutator}().");
        }

        $collection = IntCollectionFixture::of(1, 2, 3);
        $copy = $collection->toArray();
        $copy[] = 4;

        self::assertSame([1, 2, 3], $collection->toArray());
    }
}
