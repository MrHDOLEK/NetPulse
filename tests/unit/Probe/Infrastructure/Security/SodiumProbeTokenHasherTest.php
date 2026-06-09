<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Infrastructure\Security;

use App\Probe\Domain\ProbeTokenHasher;
use App\Probe\Infrastructure\Security\SodiumProbeTokenHasher;
use PHPUnit\Framework\TestCase;

final class SodiumProbeTokenHasherTest extends TestCase
{
    private SodiumProbeTokenHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new SodiumProbeTokenHasher();
    }

    public function testImplementsHasherContract(): void
    {
        $this->assertInstanceOf(ProbeTokenHasher::class, $this->hasher);
    }

    public function testHashIsNotPlaintextAndIsSalted(): void
    {
        $hashOne = $this->hasher->hash("super-secret");
        $hashTwo = $this->hasher->hash("super-secret");

        $this->assertNotSame("super-secret", $hashOne);
        $this->assertNotSame($hashOne, $hashTwo);
    }

    public function testVerifyMatchesCorrectPlaintext(): void
    {
        $hash = $this->hasher->hash("super-secret");

        $this->assertTrue($this->hasher->verify("super-secret", $hash));
    }

    public function testVerifyRejectsWrongPlaintext(): void
    {
        $hash = $this->hasher->hash("super-secret");

        $this->assertFalse($this->hasher->verify("wrong-secret", $hash));
    }
}
