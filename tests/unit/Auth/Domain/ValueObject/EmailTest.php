<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\ValueObject;

use App\Auth\Domain\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidEmails(): iterable
    {
        yield "single char" => ["x"];
        yield "empty string" => [""];
        yield "missing domain" => ["a@"];
        yield "missing local part" => ["@b"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNormalizationCases(): iterable
    {
        yield "already lowercase" => ["a@b.test", "a@b.test"];
        yield "mixed case normalized to lowercase" => ["A@B.Test", "a@b.test"];
    }

    #[DataProvider("provideInvalidEmails")]
    public function testRejectsInvalidEmail(string $invalid): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email($invalid);
    }

    public function testAcceptsValidEmail(): void
    {
        $email = new Email("a@b.test");

        self::assertSame("a@b.test", $email->value());
    }

    #[DataProvider("provideNormalizationCases")]
    public function testNormalizesToLowercase(string $input, string $expected): void
    {
        $email = new Email($input);

        self::assertSame($expected, $email->value());
        self::assertSame($expected, (string)$email);
    }

    public function testEqualsByValue(): void
    {
        self::assertTrue((new Email("A@B.Test"))->equals(new Email("a@b.test")));
        self::assertFalse((new Email("a@b.test"))->equals(new Email("c@d.test")));
    }
}
