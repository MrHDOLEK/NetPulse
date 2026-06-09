<?php

declare(strict_types=1);

namespace App\Tests\Integration\Probe;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

final class DoctrineProbeRepositoryTest extends KernelTestCase
{
    private ProbeRepository $repository;
    private EntityManagerInterface $entityManager;
    private DbalConnection $dbal;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get(ProbeRepository::class);
        self::assertInstanceOf(ProbeRepository::class, $repository);

        $this->repository = $repository;
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dbal = $container->get(DbalConnection::class);

        $this->dbal->executeStatement("DELETE FROM connections");
        $this->dbal->executeStatement("DELETE FROM probes");
    }

    public function testSavesAndGetsProbeRoundTrip(): void
    {
        $probe = $this->newProbe();
        $this->repository->save($probe);
        $this->entityManager->clear();

        $loaded = $this->repository->get(new ProbeId("550e8400-e29b-41d4-a716-446655440000"));

        $this->assertSame("edge-warsaw", $loaded->name());
        $this->assertInstanceOf(Labels::class, $loaded->labels());
        $this->assertSame(["site" => "home", "link" => "wan1"], $loaded->labels()->all());
        $this->assertSame("home", $loaded->labels()->get("site"));
        $this->assertSame("hashed-token", $loaded->tokenHash());
        $this->assertTrue($loaded->isEnabled());

        $this->assertEquals(
            new DateTimeImmutable("2026-06-05T10:00:00+00:00"),
            $loaded->createdAt(),
        );
        $this->assertSame("UTC", $loaded->createdAt()->getTimezone()->getName());
        $this->assertSame(
            "2026-06-05T10:00:00+00:00",
            $loaded->createdAt()->format("Y-m-d\TH:i:sP"),
        );
    }

    public function testFindReturnsNullForUnknownProbe(): void
    {
        $this->assertNull(
            $this->repository->find(new ProbeId("11111111-1111-1111-1111-111111111111")),
        );
    }

    public function testGetThrowsForUnknownProbe(): void
    {
        $this->expectException(ProbeNotFound::class);

        $this->repository->get(new ProbeId("22222222-2222-2222-2222-222222222222"));
    }

    public function testDeleteRemovesTheProbe(): void
    {
        $probe = $this->newProbe();
        $this->repository->save($probe);
        $this->entityManager->clear();

        $loaded = $this->repository->get(new ProbeId("550e8400-e29b-41d4-a716-446655440000"));
        $this->repository->delete($loaded);
        $this->entityManager->clear();

        $this->assertNull($this->repository->find(new ProbeId("550e8400-e29b-41d4-a716-446655440000")));
    }

    public function testAllReturnsEveryProbeNewestFirst(): void
    {
        $older = $this->newProbe("550e8400-e29b-41d4-a716-446655440010", "edge-older", new DateTimeImmutable("2026-01-01T00:00:00+00:00"));
        $newer = $this->newProbe("550e8400-e29b-41d4-a716-446655440011", "edge-newer", new DateTimeImmutable("2026-06-01T00:00:00+00:00"));

        $this->repository->save($older);
        $this->repository->save($newer);
        $this->entityManager->clear();

        $all = $this->repository->all();

        $this->assertCount(2, $all);

        $names = array_map(static fn(Probe $p): string => $p->name(), $all->toArray());

        $this->assertSame(["edge-newer", "edge-older"], $names);
    }

    private function newProbe(
        string $uuid = "550e8400-e29b-41d4-a716-446655440000",
        string $name = "edge-warsaw",
        ?DateTimeImmutable $createdAt = null,
    ): Probe {
        return new Probe(
            new ProbeId($uuid),
            $name,
            Labels::fromArray(["site" => "home", "link" => "wan1"]),
            "hashed-token",
            true,
            $createdAt ?? new DateTimeImmutable("2026-06-05T10:00:00+00:00"),
        );
    }
}
