<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidEmailAddress;
use App\Inventory\Domain\ValueObject\EmailAddress;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class EmailAddressTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'simple address'    => ['jane@example.com', 'jane@example.com'];
        yield 'lowercases input'  => ['Jane@Example.COM', 'jane@example.com'];
        yield 'trims whitespace'  => ['  jane@example.com  ', 'jane@example.com'];
        yield 'subdomain'         => ['jane@mail.example.com', 'jane@mail.example.com'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid email address: $_dataName.')]
    public function accepts_a_valid_email(string $input, string $expected): void
    {
        self::assertSame($expected, EmailAddress::of($input)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty'                 => [''];
        yield 'whitespace only'       => ['   '];
        yield 'missing at-sign'       => ['janeexample.com'];
        yield 'missing domain'        => ['jane@'];
        yield 'missing local'         => ['@example.com'];
        yield 'too long' => [str_repeat('a', 250) . '@example.com'];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid email address: $_dataName.')]
    public function rejects_invalid_email(string $value): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::of($value);
    }

    #[Test]
    #[TestDox('equals() compares by normalized value.')]
    public function equals_compares_by_normalized_value(): void
    {
        self::assertTrue(
            EmailAddress::of('Jane@Example.com')
                ->equals(EmailAddress::of('jane@example.com')),
        );
        self::assertFalse(
            EmailAddress::of('jane@example.com')
                ->equals(EmailAddress::of('john@example.com')),
        );
    }
}
