<?php

declare(strict_types=1);

namespace App\Tests\Unit\Probe\Domain;

use App\Probe\Domain\ValueObject\ProbeToken;
use PHPUnit\Framework\TestCase;

final class ProbeTokenTest extends TestCase
{
    public function testWrapsPlaintext(): void
    {
        $token = new ProbeToken("plain-secret-value");

        $this->assertSame("plain-secret-value", $token->toString());
    }

    public function testGenerateProducesUniqueUrlSafeTokens(): void
    {
        $first = ProbeToken::generate();
        $second = ProbeToken::generate();

        $this->assertNotSame($first->toString(), $second->toString());
        $this->assertMatchesRegularExpression("/^[A-Za-z0-9_-]{43}$/", $first->toString());
        $this->assertMatchesRegularExpression("/^[A-Za-z0-9_-]{43}$/", $second->toString());
    }
}
