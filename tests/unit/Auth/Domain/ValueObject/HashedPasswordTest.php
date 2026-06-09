<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\ValueObject;

use App\Auth\Domain\ValueObject\HashedPassword;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    public function testFromHashStoresTheHash(): void
    {
        $hash = "argon2id-v19-m65536-t4-p1-abcdef-hash";

        $password = HashedPassword::fromHash($hash);

        self::assertSame($hash, $password->value());
        self::assertSame($hash, (string)$password);
    }

    public function testRejectsEmptyHash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HashedPassword::fromHash("");
    }
}
