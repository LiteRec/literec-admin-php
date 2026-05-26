<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidEmailAddress;
use App\Shared\Domain\ValueObject\EmailAddress;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[Test]
    #[TestDox('Stores the lowercased trimmed value when given a valid email.')]
    public function lowercases_and_trims(): void
    {
        self::assertSame('alice@example.com', EmailAddress::of('  Alice@Example.COM  ')->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestDox('Rejects an empty input with InvalidEmailAddress.')]
    public function rejects_empty(string $input): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::of($input);
    }

    #[Test]
    #[TestWith(['not-an-email'])]
    #[TestWith(['@example.com'])]
    #[TestWith(['alice@'])]
    #[TestWith(['janeexample.com'])]
    #[TestDox('Rejects a malformed email; exception message does not echo the input.')]
    public function rejects_malformed(string $input): void
    {
        try {
            EmailAddress::of($input);
            self::fail('Expected InvalidEmailAddress.');
        } catch (InvalidEmailAddress $e) {
            self::assertStringNotContainsString($input, $e->getMessage());
        }
    }

    #[Test]
    #[TestDox('Rejects an over-254-char email; exception message does not echo the input.')]
    public function rejects_overlong(): void
    {
        $input = str_repeat('a', 250) . '@e.io';

        try {
            EmailAddress::of($input);
            self::fail('Expected InvalidEmailAddress.');
        } catch (InvalidEmailAddress $e) {
            self::assertStringNotContainsString($input, $e->getMessage());
        }
    }

    #[Test]
    #[TestDox('Accepts an email at the exact 254-char boundary.')]
    public function accepts_an_email_at_the_max_length_boundary(): void
    {
        // 254 chars total, split into RFC-valid components:
        //   local 64 + '@' + three 60-char labels separated by dots
        //   + a 4-char TLD = 64 + 1 + 60 + 1 + 60 + 1 + 60 + 1 + 6 = 254.
        $local = str_repeat('a', 64);
        $domain = sprintf(
            '%s.%s.%s.museum',
            str_repeat('b', 60),
            str_repeat('c', 60),
            str_repeat('d', 60),
        );
        $input = $local . '@' . $domain;

        self::assertSame(254, strlen($input));
        self::assertSame($input, EmailAddress::of($input)->value);
    }

    #[Test]
    #[TestDox('__toString() returns the lowercased canonical value.')]
    public function to_string_returns_the_lowercased_canonical_value(): void
    {
        self::assertSame('alice@example.com', (string) EmailAddress::of('Alice@Example.com'));
    }

    #[Test]
    #[TestDox('Equals another email with the same lowercased value.')]
    public function equals(): void
    {
        self::assertTrue(EmailAddress::of('Alice@Example.com')->equals(EmailAddress::of('alice@example.com')));
        self::assertFalse(EmailAddress::of('alice@example.com')->equals(EmailAddress::of('bob@example.com')));
    }
}
