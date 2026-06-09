<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Domain;

use App\Probe\Domain\Entity\Probe;
use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Domain\ValueObject\ProbeId;
use App\Shared\Domain\ValueObject\Labels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProbeTest extends TestCase
{
    private ProbeTokenHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new class() implements ProbeTokenHasher {
            public function hash(string $plaintext): string
            {
                return "hashed:" . $plaintext;
            }

            public function verify(string $plaintext, string $hash): bool
            {
                return $hash === "hashed:" . $plaintext;
            }
        };
    }

    public function testExposesItsFields(): void
    {
        $probe = $this->probe();

        $this->assertSame("edge-warsaw", $probe->name());
        $this->assertSame(["site" => "home", "link" => "wan1"], $probe->labels()->all());
        $this->assertSame("home", $probe->labels()->get("site"));
        $this->assertSame("hashed:secret", $probe->tokenHash());
        $this->assertTrue($probe->isEnabled());
        $this->assertSame("550e8400-e29b-41d4-a716-446655440000", $probe->id()->toString());
        $this->assertSame("2026-06-05T10:00:00+00:00", $probe->createdAt()->format(DateTimeImmutable::ATOM));
    }

    public function testVerifyTokenAcceptsMatchingPlaintext(): void
    {
        $probe = $this->probe("hashed:secret");

        $this->assertTrue($probe->verifyToken("secret", $this->hasher));
    }

    public function testVerifyTokenRejectsWrongPlaintext(): void
    {
        $probe = $this->probe("hashed:secret");

        $this->assertFalse($probe->verifyToken("nope", $this->hasher));
    }

    public function testRotateTokenSwapsTheStoredHashAndInvalidatesTheOldToken(): void
    {
        $probe = $this->probe("hashed:old");

        $this->assertTrue($probe->verifyToken("old", $this->hasher));

        $probe->rotateToken($this->hasher->hash("new"));

        $this->assertSame("hashed:new", $probe->tokenHash());
        $this->assertTrue($probe->verifyToken("new", $this->hasher));
        $this->assertFalse($probe->verifyToken("old", $this->hasher));
    }

    public function testDisableThenEnableTogglesState(): void
    {
        $probe = $this->probe(enabled: true);

        $probe->disable();
        $this->assertFalse($probe->isEnabled());

        $probe->enable();
        $this->assertTrue($probe->isEnabled());
    }

    private function probe(string $tokenHash = "hashed:secret", bool $enabled = true): Probe
    {
        return new Probe(
            new ProbeId("550e8400-e29b-41d4-a716-446655440000"),
            "edge-warsaw",
            Labels::fromArray(["site" => "home", "link" => "wan1"]),
            $tokenHash,
            $enabled,
            new DateTimeImmutable("2026-06-05T10:00:00+00:00"),
        );
    }
}
