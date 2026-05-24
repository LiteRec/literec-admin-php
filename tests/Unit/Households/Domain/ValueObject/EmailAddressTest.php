<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidEmailAddress;
use App\Households\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class EmailAddressTest extends TestCase
{
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
    #[TestDox('Equals another email with the same lowercased value.')]
    public function equals(): void
    {
        self::assertTrue(EmailAddress::of('Alice@Example.com')->equals(EmailAddress::of('alice@example.com')));
        self::assertFalse(EmailAddress::of('alice@example.com')->equals(EmailAddress::of('bob@example.com')));
    }
}
