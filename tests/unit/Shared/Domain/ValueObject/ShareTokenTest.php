<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ShareToken;
use PHPUnit\Framework\TestCase;

final class ShareTokenTest extends TestCase
{
    public function testGenerateProducesA43CharUrlSafeToken(): void
    {
        $token = ShareToken::generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token->toString());
    }

    public function testGenerateProducesUniqueTokens(): void
    {
        $first = ShareToken::generate();
        $second = ShareToken::generate();

        $this->assertNotSame($first->toString(), $second->toString());
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $second->toString());
    }
}
