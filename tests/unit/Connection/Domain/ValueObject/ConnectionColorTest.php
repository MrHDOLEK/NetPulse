<?php

declare(strict_types=1);

namespace App\Tests\Unit\Connection\Domain\ValueObject;

use App\Connection\Domain\Enum\ConnectionColor;
use PHPUnit\Framework\TestCase;

final class ConnectionColorTest extends TestCase
{
    public function testHasThreeAccents(): void
    {
        $this->assertSame('primary', ConnectionColor::Primary->value);
        $this->assertSame('violet', ConnectionColor::Violet->value);
        $this->assertSame('amber', ConnectionColor::Amber->value);
    }

    public function testCanBeBuiltFromValue(): void
    {
        $this->assertSame(ConnectionColor::Amber, ConnectionColor::from('amber'));
    }

    public function testDefaultsToPrimary(): void
    {
        $this->assertSame(ConnectionColor::Primary, ConnectionColor::default());
    }
}
