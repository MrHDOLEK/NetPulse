<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Infrastructure\Symfony\Security;

use App\Connection\Domain\ConnectionCollection;
use App\Connection\Domain\ConnectionRepository;
use App\Connection\Domain\Entity\Connection;
use App\Connection\Domain\Enum\ConnectionColor;
use App\Connection\Domain\Exception\ConnectionNotOwnedByProbe;
use App\Connection\Domain\ValueObject\AdaptivePolicy;
use App\Connection\Domain\ValueObject\ConnectionId;
use App\Connection\Domain\ValueObject\ExpectedSpeed;
use App\Connection\Domain\ValueObject\Schedule;
use App\Connection\Domain\ValueObject\ServerPool;
use App\Connection\Domain\ValueObject\Thresholds;
use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\Exception\InvalidProbeToken;
use App\Probe\Domain\Exception\ProbeDisabled;
use App\Probe\Domain\Exception\ProbeNotFound;
use App\Probe\Domain\ProbeCollection;
use App\Probe\Domain\ProbeRepository;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Probe\Infrastructure\Symfony\Security\ProbeTokenResolver;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ProbeTokenResolverTest extends TestCase
{
    private const PROBE_ID = "22222222-2222-4222-8222-222222222222";
    private const CONNECTION_ID = "33333333-3333-4333-8333-333333333333";

    public function testResolvesProbeForValidToken(): void
    {
        $resolver = $this->resolver(
            $this->probe(true),
            [self::CONNECTION_ID => $this->connection(new ProbeId(self::PROBE_ID))],
            $this->hasher(),
        );

        $request = $this->request("Bearer secret", json_encode(["connectionId" => self::CONNECTION_ID]));

        $resolved = iterator_to_array($resolver->resolve($request, $this->metadata()));

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(Probe::class, $resolved[0]);
        $this->assertTrue($resolved[0]->id()->equals(new ProbeId(self::PROBE_ID)));
        $this->assertSame($resolved[0], $request->attributes->get("_probe"));
    }

    public function testThrowsInvalidProbeTokenWhenHeaderMissing(): void
    {
        $resolver = $this->resolver($this->probe(true), [], $this->hasher());

        $this->expectException(InvalidProbeToken::class);

        iterator_to_array($resolver->resolve($this->request(null, null), $this->metadata()));
    }

    public function testThrowsInvalidProbeTokenWhenTokenWrong(): void
    {
        $resolver = $this->resolver($this->probe(true), [], $this->hasher());

        $this->expectException(InvalidProbeToken::class);

        iterator_to_array($resolver->resolve($this->request("Bearer wrong", null), $this->metadata()));
    }

    public function testThrowsProbeDisabledWhenProbeDisabled(): void
    {
        $resolver = $this->resolver($this->probe(false), [], $this->hasher());

        $this->expectException(ProbeDisabled::class);

        iterator_to_array($resolver->resolve($this->request("Bearer secret", null), $this->metadata()));
    }

    public function testThrowsConnectionNotOwnedWhenForeignConnection(): void
    {
        $foreignOwner = new ProbeId("44444444-4444-4444-8444-444444444444");
        $resolver = $this->resolver(
            $this->probe(true),
            [self::CONNECTION_ID => $this->connection($foreignOwner)],
            $this->hasher(),
        );

        $request = $this->request("Bearer secret", json_encode(["connectionId" => self::CONNECTION_ID]));

        $this->expectException(ConnectionNotOwnedByProbe::class);

        iterator_to_array($resolver->resolve($request, $this->metadata()));
    }

    public function testThrowsConnectionNotOwnedWhenConnectionUnknown(): void
    {
        $resolver = $this->resolver($this->probe(true), [], $this->hasher());

        $request = $this->request("Bearer secret", json_encode(["connectionId" => self::CONNECTION_ID]));

        $this->expectException(ConnectionNotOwnedByProbe::class);

        iterator_to_array($resolver->resolve($request, $this->metadata()));
    }

    private function hasher(): ProbeTokenHasher
    {
        return new class() implements ProbeTokenHasher {
            public function hash(string $plaintext): string
            {
                return "hash:" . $plaintext;
            }

            public function verify(string $plaintext, string $hash): bool
            {
                return $hash === "hash:" . $plaintext;
            }
        };
    }

    private function probe(bool $enabled): Probe
    {
        return new Probe(
            new ProbeId(self::PROBE_ID),
            "home",
            Labels::fromArray(["site" => "home"]),
            "hash:secret",
            $enabled,
            new DateTimeImmutable(),
        );
    }

    private function connection(ProbeId $owner): Connection
    {
        return new Connection(
            new ConnectionId(self::CONNECTION_ID),
            $owner,
            "wan1",
            "Orange",
            new ExpectedSpeed(1_000_000_000, 1_000_000_000),
            ConnectionColor::Primary,
            Labels::empty(),
            ServerPool::empty(),
            Schedule::even(24, 120),
            true,
            Thresholds::default(),
            AdaptivePolicy::default(),
        );
    }

    /**
     * @param array<string,Connection> $byId
     */
    private function resolver(Probe $probe, array $byId, ProbeTokenHasher $hasher): ProbeTokenResolver
    {
        $probeRepo = new class($probe) implements ProbeRepository {
            public function __construct(
                private readonly Probe $probe,
            ) {}

            public function save(Probe $probe): void
            {
            }

            public function delete(Probe $probe): void
            {
            }

            public function get(ProbeId $id): Probe
            {
                if (!$id->equals($this->probe->id())) {
                    throw new ProbeNotFound();
                }

                return $this->probe;
            }

            public function find(ProbeId $id): ?Probe
            {
                return $id->equals($this->probe->id()) ? $this->probe : null;
            }

            public function all(): ProbeCollection
            {
                return ProbeCollection::of($this->probe);
            }
        };

        $connectionRepo = new class($byId) implements ConnectionRepository {
            /**
             * @param array<string,Connection> $byId
             */
            public function __construct(
                private readonly array $byId,
            ) {}

            public function save(Connection $connection): void
            {
            }

            public function delete(Connection $connection): void
            {
            }

            public function get(ConnectionId $id): Connection
            {
                return $this->byId[$id->toString()];
            }

            public function find(ConnectionId $id): ?Connection
            {
                return $this->byId[$id->toString()] ?? null;
            }

            public function byProbe(ProbeId $probeId): ConnectionCollection
            {
                return ConnectionCollection::fromList(array_values($this->byId));
            }

            public function allEnabled(): ConnectionCollection
            {
                return ConnectionCollection::fromList(array_values($this->byId));
            }

            public function all(): ConnectionCollection
            {
                return ConnectionCollection::fromList(array_values($this->byId));
            }
        };

        return new ProbeTokenResolver($probeRepo, $connectionRepo, $hasher);
    }

    private function request(?string $authorization, ?string $body): Request
    {
        $server = [];

        if ($authorization !== null) {
            $server["HTTP_AUTHORIZATION"] = $authorization;
        }

        $request = Request::create("/api/v1/probes/" . self::PROBE_ID . "/results", "POST", [], [], [], $server, $body);
        $request->attributes->set("probeId", self::PROBE_ID);

        return $request;
    }

    private function metadata(): ArgumentMetadata
    {
        return new ArgumentMetadata("probe", Probe::class, false, false, null);
    }
}
